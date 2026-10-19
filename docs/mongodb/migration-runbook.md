# Runbook chuyển MySQL → MongoDB

Phạm vi: bản MongoDB độc lập, hybrid như [physical design](physical-design.md). Chuyển **toàn bộ row trong snapshot**, gồm business data và runtime archive; giữ private uploads. Bản SQL gốc và database MySQL không thay đổi. Không commit/push/thêm remote trong quá trình vận hành này; mọi thao tác xuất bản cần chỉ dẫn mới của chủ dự án.

## 1. Chuẩn bị và kiểm kê

Dùng PowerShell, đặt đúng hai đường dẫn riêng biệt:

```powershell
$sourceRoot = 'D:\Config_environment\laragon\laragon\www\Sai-Gon-CMC-Laravel-MySQL'
$mongoRoot = 'D:\Config_environment\laragon\laragon\www\Sai-Gon-CMC-Laravel-MongoDB'
$snapshotPath = Join-Path $mongoRoot '.runtime\mysql-snapshot-20261018'
Set-Location $mongoRoot
```

Giá trị snapshot trên là đợt đã chụp. Nếu xuất đợt mới, chọn thư mục **chưa tồn tại** dưới `.runtime/`; không ghi đè manifest/snapshot cũ. Ghi lại source HEAD/status/remote, schema/table engines, thời điểm chụp và bộ hash upload để so trước/sau; không đưa nội dung `.env`, password hashes, session payload hoặc file riêng tư vào log công khai.

Nguồn MySQL cần các business tables InnoDB/transactional để repeatable-read snapshot bao phủ nhất quán. Tránh thay DDL trong lúc export. Nếu đây là lần chuyển dữ liệu cuối cùng để sử dụng tiếp, tạm ngừng ghi nguồn và upload trong cửa sổ chụp/copy; read-only snapshot không đồng bộ các giao dịch phát sinh sau thời điểm chụp. Dữ liệu file không nằm trong SQL transaction nên cần giữ file ổn định đến khi hash/copy xong.

## 2. Runtime của bản MongoDB

Chạy setup từ README trước, sau đó:

```powershell
.\tools\mongodb-local.ps1 Status
.\tools\php-mongodb.cmd --ri mongodb
Set-Location laravel
..\tools\php-mongodb.cmd artisan cmms:import-mysql-snapshot --help --no-interaction
```

Replica set phải `primary:true`, set `cmcMongoRs`, `127.0.0.1:27028`. DB đích trong `.env` phải là database **riêng** như `cmc_mongodb`; không dùng tên `cmc_mongo_test_*` cho dữ liệu thật. `.runtime/mongodb/data` khác hoàn toàn dịch vụ/database nguồn. Wrapper không sửa global `php.ini`; đừng chạy PHP không có extension này cho các bước MongoDB.

`setup-mongodb-runtime.ps1` tải archive extension chính thức 2.5.5 và kiểm SHA256 `F94D4DA6A8FB126FB2A234F0D309A20B5C2E69FBE1805A4DFD2FFE570389FC59`, rồi `composer install --no-scripts` từ lockfile. Có thể chạy lại để phục hồi dependency/runtime; nó không reset database. Tham số `-PhpBinary`/`-ComposerPhar` cho phép đường dẫn cài khác; setup kiểm PHP 8.3 TS x64 và tạo lại wrapper tương ứng. `mongodb-local.ps1 Start` giữ tiến trình hiện có nếu đúng PID/executable/dbpath; `Status` chỉ đọc. Không stop/restart trong lúc import đang chạy.

## 3. Xuất snapshot SQL chỉ đọc

Bước này chỉ dùng khi chưa có snapshot hợp lệ. Từ gốc bản MongoDB:

```powershell
Set-Location $mongoRoot
.\tools\php-mongodb.cmd tools/export-mysql-snapshot.php "$sourceRoot\laravel" "$snapshotPath"
```

Exporter khởi tạo source application để đọc cấu hình hiện có, kết nối MySQL, dùng `REPEATABLE READ` và `START TRANSACTION WITH CONSISTENT SNAPSHOT, READ ONLY`. Nó liệt kê **tất cả base tables** và ghi mỗi bảng thành NDJSON, đồng thời lưu metadata cột/primary key, row count và SHA256 trong `manifest.json`. Không xuất credential ra terminal. Tham số thứ ba tùy chọn là port MySQL riêng cho lần chạy, không sửa `.env` nguồn; chỉ dùng khi cần trỏ đúng instance nguồn được xác nhận.

Nếu export lỗi, transaction nguồn rollback và thư mục có thể còn file chưa hoàn chỉnh. Giữ nó để điều tra, chọn thư mục mới cho lần xuất sau; importer từ chối snapshot thiếu manifest/hash/count không khớp. Không sửa NDJSON để ép import.

Snapshot đầu tiên có 40 bảng, 342 row: business 303, runtime archive 39. Đây là baseline của đợt chụp 18/10/2026, không là số cố định cho mọi lần export. Manifest thực tế quyết định tổng kiểm kê, kể cả bảng rỗng.

## 4. Giữ private uploads và manifest riêng

Copy file trong `sourceRoot/laravel/storage/app/` sang **đúng relative path** ở bản MongoDB, đặc biệt `private/contracts/`. Không copy `.env`, source PHP config, cache, session files hoặc queue worker state. Không tạo public symlink tới private contracts. Snapshot SQL chỉ giữ path/reference của file, không chứa nội dung upload.

Có thể dùng đoạn PowerShell này sau khi đã giữ uploads nguồn ổn định. Nó không ghi đè file đích khác hash; manifest đặt trong `.runtime/`, không chứa credential:

```powershell
$sourceUploads = Join-Path $sourceRoot 'laravel\storage\app'
$targetUploads = Join-Path $mongoRoot 'laravel\storage\app'
$uploadManifest = @(Get-ChildItem -LiteralPath $sourceUploads -Recurse -File |
    Where-Object { $_.Name -ne '.gitignore' } | ForEach-Object {
        $relativePath = [IO.Path]::GetRelativePath($sourceUploads, $_.FullName)
        $targetPath = Join-Path $targetUploads $relativePath
        $sourceHash = (Get-FileHash -LiteralPath $_.FullName -Algorithm SHA256).Hash
        if (Test-Path -LiteralPath $targetPath) {
            if ((Get-FileHash -LiteralPath $targetPath -Algorithm SHA256).Hash -ne $sourceHash) {
                throw "Upload đích đã khác nguồn; dừng để đối chiếu manifest."
            }
        } else {
            New-Item -ItemType Directory -Path (Split-Path -Parent $targetPath) -Force | Out-Null
            Copy-Item -LiteralPath $_.FullName -Destination $targetPath
        }
        if ((Get-FileHash -LiteralPath $targetPath -Algorithm SHA256).Hash -ne $sourceHash) {
            throw 'Hash upload sau copy không khớp.'
        }
        [PSCustomObject]@{ path = $relativePath; bytes = $_.Length; sha256 = $sourceHash }
    })
$manifestPath = Join-Path $mongoRoot ('.runtime\upload-manifest-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.json')
$uploadManifest | ConvertTo-Json -Depth 4 | Set-Content -LiteralPath $manifestPath -Encoding utf8
```

Lưu manifest cùng backup riêng tư. Đợt hiện có đã lưu `.runtime/upload-manifest.json` cho bốn contract PDFs; kiểm lại hash khi phục hồi và thử quyền tải file qua ứng dụng. Hash chứng minh file copy giống nguồn, không thay kiểm tra owner/role của endpoint.

## 5. Tạo schema, dry run và import

Đặt `.env` bản MongoDB theo README; giữ `APP_KEY` đã có. Chạy từ `laravel/`, **không seed** và chưa cho người dùng tạo/sửa business data:

```powershell
Set-Location "$mongoRoot\laravel"
..\tools\php-mongodb.cmd artisan package:discover --no-interaction
..\tools\php-mongodb.cmd artisan migrate --no-interaction
..\tools\php-mongodb.cmd artisan cmms:import-mysql-snapshot "$snapshotPath" --dry-run --no-interaction
..\tools\php-mongodb.cmd artisan cmms:import-mysql-snapshot "$snapshotPath" --no-interaction
..\tools\php-mongodb.cmd artisan cmms:import-mysql-snapshot "$snapshotPath" --verify --no-interaction
```

Dry run kiểm manifest/hash/count/schema và khả năng encode kiểu; không thay business records, nhưng không thay thế kiểm foreign references, balance hoặc transaction/concurrency. `migrate` tạo BSON schema/index và migration metadata riêng; metadata/runtime mới không được nhầm với business data nguồn cần import.

Importer yêu cầu business target rỗng khi bắt đầu snapshot mới. Nó từ chối target đã có snapshot khác hoặc business data; không ghi đè. Parent documents nhập trước, bốn bảng item vào đúng `items[]` với item_id cũ. `_counters` tăng tới ID đã nhập. Password hashes và field gốc được giữ qua mapping kiểu.

Tám bảng runtime nguồn (migrations, sessions, password_reset_tokens, cache, cache_locks, jobs, job_batches, failed_jobs) đi vào `_legacy_runtime_archive` nguyên payload, keyed theo snapshot/table/position. Do đó **không mất row** nhưng cũng không login lại phiên cũ hoặc phát lại job nguồn. Archive có thể chứa dữ liệu nhạy cảm giống backup gốc; chỉ giữ trong môi trường riêng tư.

Import không là một transaction khổng lồ cho toàn snapshot. Nếu bị gián đoạn, giữ nguyên snapshot và `_snapshot_imports`, chạy lại đúng lệnh import: row đã có phải khớp, row còn thiếu được bổ sung, archive upsert theo khóa cố định. Nếu đã cho nghiệp vụ thay đổi sau import, không dùng import để đồng bộ lại; hãy giữ backup và dùng database đích mới nếu cần làm lại.

## 6. Kiểm chứng trước bàn giao

`--verify` chỉ đọc application records: so tổng logical rows (kể cả item nhúng), từng field nguồn sau chuẩn hóa kiểu và từng archived payload; kiểm lại snapshot checksum trước khi so. Với snapshot đầu tiên, kết quả kỳ vọng:

```json
{"verified":true,"tables":40,"business_rows":303,"archived_runtime_rows":39}
```


Bằng chứng đợt chuyển hiện tại: import/verify 40 bảng, 303 business rows và 39 archive rows; bốn private upload hashes khớp; đối chiếu 592 foreign-reference values không có orphan ở nguồn/đích và tổng 45 decimal fields khớp chính xác. HTTP `/`, `/login`, `/products`, `/cart` trả 200 trên `127.0.0.1:8011`. Đây là bằng chứng cho snapshot/luồng đã kiểm, không thay full-suite hoặc endpoint authorization tests; full-suite cuối đã pass 110 tests / 1060 assertions, exit 0, 61.272 giây sau Pint (bằng chứng riêng tư `.runtime/final-suite.xml` và `.runtime/final-suite.log`). Mười CSS/JS assets local cũng trả 200.
Không coi con số này là toàn bộ nghiệm thu. Cần lưu bằng chứng riêng cho references/owner, stock theo kho, nghĩa vụ phải thu/phải trả, payment/credit balances, period reports và contract files. Đối chiếu baseline nguồn để phân biệt dữ liệu nguồn đã thiếu liên kết với sai lệch do chuyển đổi. Không log nội dung user/password/session.

Chạy đầy đủ PHPUnit trong database test tách biệt, không vào business database:

```powershell
..\tools\php-mongodb.cmd vendor/bin/phpunit
```

`phpunit.xml` dùng `cmc_mongo_test_suite`; test reset guard chỉ chấp nhận `APP_ENV=testing` và tên `cmc_mongo_test_*`. CI khởi tạo MongoDB 8.3 replica set thật và chạy full suite. Cần có bằng chứng native transaction/rollback và cạnh tranh; không thay bằng test SQLite. Giới hạn còn lại: BSON document tối đa 16 MiB, ICU/search là phép gần đúng so với collation SQL, và replica set local một node không có HA. Không tự ghi "CI passed" trước khi workflow thực sự chạy; bản workflow trong repo được static validation riêng.

Sau verify, chạy web theo README, kiểm auth/role, số lượng lẻ, một chứng từ đã nhập và tải upload đúng quyền. Đăng nhập có thể tạo **runtime mới**; không làm thay archive gốc. Bất kỳ thay đổi business nào sau verify là dữ liệu của bản MongoDB, không tự đồng bộ ngược SQL.

So lại source HEAD/status/remote, database nguồn và upload hashes với baseline. Bản clone ban đầu không có remote. Repo xuất bản mới được chủ dự án cho phép là `https://github.com/nhattoan235/SaiGon-CMC-MongoDB.git`; không dùng repo SQL gốc làm đích push. Việc kiểm chứng dữ liệu không tự cấp quyền cho lần push tiếp theo.

## 7. Phục hồi và vận hành

Giữ snapshot NDJSON/manifest, upload manifest/files và bản backup MongoDB ở vị trí riêng tư ngoài Git trước khi mất máy/thay ổ đĩa. `.runtime/` là thư mục bị ignore, **không được khôi phục chỉ từ source code**. Với MongoDB Database Tools, backup replica set có thể dùng `mongodump` theo URI riêng và lựa chọn snapshot/oplog phù hợp; xác nhận quy trình restore trên instance/database mới trước sử dụng thật.

Nếu bản MongoDB chưa đạt nghiệm thu, dừng truy cập bản đó và tiếp tục dùng SQL gốc được giữ riêng. Nếu MongoDB đã có giao dịch mới, lưu backup và đối soát/chuyển những giao dịch đó trước quyết định quay lại SQL; không coi database SQL là bản sao cập nhật. Không xóa target, archive, counters hoặc rollback source để sửa một lần import.

`tools/mongodb-local.ps1 Stop` chỉ dừng tiến trình có đúng PID/executable/dbpath thuộc bản clone; không xóa data. `Start`/`Status` dùng lại thư mục dữ liệu đó. Triển khai production cần replica set được quản trị, xác thực, TLS, mạng hạn chế và secret ngoài Git; cấu hình loopback không có auth trong repo chỉ dùng phát triển local/CI. [Hướng dẫn replica set](https://www.mongodb.com/docs/manual/tutorial/convert-standalone-to-replica-set/).
