# Sài Gòn CMC — Laravel + MongoDB

Bản chuyển đổi độc lập của ứng dụng quản lý vật liệu xây dựng, dùng **Laravel 13, PHP 8.3 và MongoDB replica set**. Thư mục SQL gốc `Sai-Gon-CMC-Laravel-MySQL` và database MySQL vẫn được giữ nguyên. Bản này dùng nhánh `codex/mongodb-migration`; không thêm remote hoặc push khi chưa có chỉ dẫn mới của chủ dự án.

Thiết kế hybrid nhúng các dòng báo giá, đơn hàng, đơn mua và chứng từ kho trong document cha. Những sổ phát sinh, công nợ, thanh toán và lịch sử giao nhận dùng collection tham chiếu. Giữ ID số, tiền/lượng Decimal128 và toàn bộ dữ liệu snapshot SQL, kể cả runtime cũ trong kho lưu trữ riêng. Xem [chỉ mục tài liệu MongoDB](docs/mongodb/README.md).

## Chạy trên máy Windows/Laragon hiện tại

Cần PHP 8.3 TS x64 VS16, Composer, MongoDB Server 8.3 và `mongosh`. Script setup mặc định dùng PHP/Composer Laragon trên ổ D; nếu cài ở nơi khác, truyền `-PhpBinary <php.exe> -ComposerPhar <composer.phar>` để tạo lại wrapper theo đường dẫn đó. PHP phải là bản 8.3 thread-safe x64 phù hợp DLL. Script MongoDB dùng `C:\Program Files\MongoDB\Server\8.3`; sửa đường dẫn trong script nếu cài server ở nơi khác. Không cần Docker cho môi trường local này.

Từ thư mục gốc **bản MongoDB**:

```powershell
.\tools\setup-mongodb-runtime.ps1
.\tools\mongodb-local.ps1 Start
.\tools\mongodb-local.ps1 Status
Set-Location laravel
if (-not (Test-Path .env)) { Copy-Item .env.example .env }
if (-not (Select-String -Path .env -Pattern '^APP_KEY=.+$' -Quiet)) { ..\tools\php-mongodb.cmd artisan key:generate --no-interaction }
..\tools\php-mongodb.cmd artisan package:discover --no-interaction
```

Chỉ tạo `APP_KEY` cho môi trường mới chưa có key; giữ key hiện tại khi tiếp tục dùng bản đã chuyển đổi. Script setup tải extension MongoDB chính thức 2.5.5, kiểm SHA256, cài đúng `composer.lock` với `--no-scripts`; PHP wrapper chỉ nạp extension MongoDB/zip cho tiến trình dự án, không sửa `php.ini` toàn máy.

Kiểm tra `.env` của bản MongoDB:

```dotenv
DB_CONNECTION=mongodb
DB_URL=
MONGODB_URI=mongodb://127.0.0.1:27028/?replicaSet=cmcMongoRs
MONGODB_DATABASE=cmc_mongodb
SESSION_DRIVER=mongodb
CACHE_STORE=mongodb
QUEUE_CONNECTION=mongodb
QUEUE_FAILED_DRIVER=mongodb
```

Replica set local `cmcMongoRs` chỉ lắng nghe `127.0.0.1:27028`; dữ liệu, log và PID nằm trong `.runtime/mongodb/` được Git bỏ qua. Môi trường triển khai thực tế phải dùng replica set có xác thực, giới hạn mạng và TLS; URI/secret riêng đặt trong biến môi trường.

**Chọn một nguồn dữ liệu trước khi chạy:**

- Đã chuyển snapshot: giữ nguyên database và upload của bản MongoDB; không seed hoặc reset lại.
- Chuyển dữ liệu hiện có: làm theo [migration runbook](docs/mongodb/migration-runbook.md), tạo schema rồi import snapshot vào database đích rỗng. Không chạy seed trước import.
- Database demo mới hoàn toàn: chạy `..\tools\php-mongodb.cmd artisan migrate --seed --no-interaction`, rồi dùng `..\tools\php-mongodb.cmd artisan cmms:create-admin admin` để nhập tên/mật khẩu quản trị viên tương tác.

Chạy web bằng chính wrapper để tiến trình server nạp extension local:

```powershell
Set-Location public
..\..\tools\php-mongodb.cmd -S 127.0.0.1:8011 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

Mở [http://127.0.0.1:8011](http://127.0.0.1:8011). Đặt `APP_URL` tương ứng. Nếu dùng hàng đợi, chạy cửa sổ khác trong `laravel/` với `..\tools\php-mongodb.cmd artisan queue:work mongodb --tries=3`. Điền Groq/mail credentials trong `.env` khi cần; không ghi chúng vào Git.

## Kiểm tra

Từ `laravel/`, chạy PHPUnit trực tiếp qua wrapper để extension không bị mất khi sinh tiến trình PHP con:

```powershell
..\tools\php-mongodb.cmd vendor/bin/phpunit
```

Test dùng MongoDB thật và chỉ được xóa database mang tiền tố `cmc_mongo_test_`; `phpunit.xml` mặc định dùng `cmc_mongo_test_suite`. Không trỏ test vào `cmc_mongodb` hoặc dữ liệu đã import. [CI](.github/workflows/mongodb.yml) chuẩn bị replica set thật và chạy toàn bộ PHPUnit; không có fallback SQLite.

Từ gốc repo, `./tools/mongodb-local.ps1 Stop` dừng đúng tiến trình được theo dõi của bản MongoDB, giữ dữ liệu trên đĩa.

## Nghiệp vụ và tài liệu

Cửa hàng cho khách, bảy vai trò, báo giá → hợp đồng → thanh toán → xuất kho → giao nhận, đổi trả, mua/nhập nhiều đợt và báo cáo được giữ theo ứng dụng gốc. Ảnh giao diện có trong `docs/screenshots/`. Dữ liệu minh họa không thay thế xác nhận giá, định mức hoặc tồn kho thực tế.

- [Thiết kế vật lý và ánh xạ dữ liệu](docs/mongodb/physical-design.md)
- [Chuyển dữ liệu, kiểm chứng và phục hồi](docs/mongodb/migration-runbook.md)
- [Chi tiết ứng dụng](laravel/README.md)
- [Ledger quyết định và chỉ mục lịch sử](docs/mongodb/README.md)
