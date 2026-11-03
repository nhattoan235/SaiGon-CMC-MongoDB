# Thiết kế backup MongoDB và cloud

Ngày: 21/10/2026.
Trạng thái: Đã triển khai và setup local theo kế hoạch được duyệt; đã kiểm thử phục hồi riêng. Chưa có OAuth client/tài khoản Google được kết nối, chưa upload Drive; lịch vẫn tắt. Xem docs/mongodb/backup-runbook.md để theo dõi bằng chứng hiện tại.
Phạm vi: chỉ bản Sai-Gon-CMC-Laravel-MongoDB; không sửa bản SQL, không push GitHub khi chưa được yêu cầu tiếp.

## 1. Yêu cầu đã xác nhận

Người dùng muốn trang quản trị cho phép bấm sao lưu, xem danh sách/tải backup và tự động sao lưu mỗi ngày. Đích là cloud; người dùng chưa có dịch vụ và yêu cầu đề xuất dễ dùng.

Mục tiêu là phục hồi được cả database lẫn tệp chứng từ khi hỏng/mất máy. Sao lưu chỉ được coi hoàn tất khi bản mã hóa đã được tải lên cloud và kiểm tra lại; có file trên máy không đồng nghĩa đã có backup cloud.

## 2. Dịch vụ đã chọn và lựa chọn thiết kế

Người dùng đã chọn Google Drive miễn phí sau khi loại phương án R2 do lo ngại tính phí tự động. Mục tiêu là đồ án môn học, dữ liệu ít, không yêu cầu thẻ thanh toán, không liên kết Cloud Billing và không đăng ký Google One hoặc bản dùng thử trả phí. Ứng dụng không tự mua dung lượng hoặc nâng cấp dịch vụ. Hạn mức Drive dùng chung Gmail/Photos; khi đầy thì upload thất bại và báo người dùng, không tiếp tục retry vô hạn.

Dùng Drive API v3 với OAuth 2.0 của tài khoản người dùng, scope duy nhất https://www.googleapis.com/auth/drive.file. Ứng dụng tự tạo thư mục SaiGon-CMC-Backups và thư mục riêng cho instance trong My Drive; không xin quyền đọc/xóa toàn bộ Drive, không chọn thư mục tùy ý và không chia sẻ công khai. Thư mục/tệp do app tạo vẫn hiển thị trong Drive để người dùng quản lý.

Không dùng service account cho My Drive cá nhân: tài liệu Google nêu service account không có storage quota và không sở hữu được file. Không yêu cầu Google Workspace hoặc Shared Drive. Dùng Laravel HTTP client đang có để gọi các REST endpoint OAuth/Drive; không cài adapter S3 hoặc SDK mới trong phạm vi thiết kế này.

Kết nối cần OAuth client dạng Web application, consent screen và redirect URI khớp địa chỉ ứng dụng. Request access_type=offline để chạy theo lịch mà không cần mở trình duyệt. Callback kiểm state một lần có hạn dùng, gắn đúng session/admin khởi tạo và kiểm quyền admin active cả lúc bắt đầu lẫn callback. Không ghép redirect URI từ Host header không tin cậy. Token/secret không vào URL log, chat, UI hoặc Git.

Refresh token lưu mã hóa bằng Laravel Crypt trong file private ngoài nguồn backup, ghi atomic/quyền hạn chế; access token ngắn hạn và refresh được đồng bộ để tránh race. Không ghi đè refresh token cũ khi Google không trả token mới. invalid_grant hoặc bị thu hồi quyền chuyển sang trạng thái Cần kết nối lại; không báo backup thành công. Ngắt kết nối yêu cầu POST/CSRF, thu hồi token và bỏ credentials local nhưng giữ file backup. Lệnh phục hồi nhận token/cấu hình riêng ngoài gói khi máy nguồn mất; không phụ thuộc database chính.

Lưu ý vận hành: Google OAuth External ở trạng thái Testing có refresh token hết hạn sau 7 ngày với scope Drive. Hướng dẫn phải ghi rõ giới hạn này; khi thiết lập lâu dài, dùng publishing status phù hợp và kiểm chính sách/verification Google thực tế, không hứa token vĩnh viễn. Nếu quyền bị thu hồi hoặc hết hạn, admin đăng nhập cấp quyền lại. Khi mất OAuth client/quyền đối với file cũ, vẫn tải thủ công trong Drive và phục hồi bằng artifact local; không xin scope toàn Drive để tìm lại file.

Mặc định: backup lúc 20:00 giờ Việt Nam, giữ 7 bản cloud thành công gần nhất và tối đa 2 bản local đã hoàn tất. Không xóa bản đang chạy/upload lỗi cần retry. Chỉ dọn bản cũ sau khi bản mới đã upload và xác minh; nếu thiếu chỗ cho bản mới, giữ bản cũ và yêu cầu xử lý dung lượng. Không tự xóa các tệp khác của người dùng hoặc dọn thùng rác toàn Drive.

## 2.1. Chiến lược full backup và tiêu chí phục hồi

Phiên bản đầu dùng full logical backup mỗi ngày trong phạm vi ứng dụng: toàn bộ dữ liệu bền vững của database được chỉ định, indexes/options và các tệp upload, với ngoại lệ runtime/secrets đã nêu ở mục 4. Mỗi artifact độc lập; khôi phục chỉ cần một artifact đã xác minh, khóa giải mã và cấu hình triển khai được giữ riêng. Không phải bản sao toàn bộ MongoDB instance hoặc ổ đĩa.

Phân biệt: full chứa toàn bộ dữ liệu trong phạm vi đã chọn; incremental chứa thay đổi từ lần backup trước, phục hồi cần full gốc và mọi incremental liên quan; differential chứa thay đổi từ full gốc, phục hồi cần full gốc và differential đã chọn. Nén file full hoặc bỏ upload khi hash không đổi không biến mongodump thành incremental database backup. Không dùng updated_at làm change log vì không bảo đảm ghi nhận delete, thay đổi schema/index và mọi writer.

Lý do chọn full: dữ liệu đồ án nhỏ, dễ kiểm thử phục hồi, không có chuỗi phụ thuộc có thể bị thiếu và retention 7 bản có thể quản lý từng bản độc lập. Quyết định cần được kiểm chứng bằng kích thước artifact nén/mã hóa, thời gian capture, thời gian upload và thời gian restore thực tế; không kết luận chỉ từ dung lượng database thô hoặc giả định miễn phí.

RPO mục tiêu khoảng 24 giờ khi máy/scheduler/database/cloud hoạt động liên tục; dashboard hiển thị tuổi của bản chụp mới nhất đã xác minh trên cloud. Khi máy tắt, Drive lỗi hoặc token hết hạn, RPO thực tế tăng và phải báo quá hạn. Thời điểm upload hoàn tất không thay thời điểm capture. RTO được đo từ lúc bắt đầu quy trình phục hồi với artifact/key/công cụ sẵn sàng đến khi database và tệp phục hồi vượt kiểm tra; ghi riêng thời gian download, restore, kiểm tra và chuyển ứng dụng. Chưa có benchmark nên chưa công bố RTO đạt được.

Nghiệm thu phải có restore drill vào database/thư mục riêng với dữ liệu test chứa cập nhật và xóa, Decimal128/Date, embedded items, indexes/options/counters và file hợp đồng; so sánh manifest/hash và kiểm nghiệp vụ sau restore. Hash cloud đúng chỉ chứng minh artifact truyền nguyên vẹn, không thay cho restore drill. Giữ ít nhất một bản local đã xác minh và bản cloud, cùng khóa phục hồi ở nơi riêng; không gọi đây là đáp ứng đầy đủ 3-2-1 khi chưa xác nhận loại phương tiện và sự độc lập của các bản.

Nếu full dump/cửa sổ chặn ghi/thời gian upload vượt ngưỡng vận hành hoặc dung lượng Drive không còn đáp ứng, đánh giá lại công nghệ backup. Hướng nâng cấp là snapshot nhất quán và change log/oplog có quản lý chuỗi, retention theo dependency, xử lý delete/DDL, phát hiện đứt chuỗi và full mới; hoặc dịch vụ backup/PITR chuyên dụng. Không tự viết incremental bằng truy vấn timestamp. mongodump --oplog phục vụ tính nhất quán trong cửa sổ dump, không tự tạo một hệ thống incremental/PITR liên tục; không kết hợp với --db. Đây là hướng đánh giá khi cần, không phải chức năng đã triển khai.

## 3. Giao diện và quyền

Thêm mục **Sao lưu dữ liệu** trong nhóm Hệ thống của sidebar, chỉ tài khoản admin đang active được truy cập. Kiểm quyền ở server cho trang, API trạng thái, tạo, retry và tải; ẩn menu không thay kiểm quyền.

Giao diện tiếp tục dùng layout staff, DM Sans, Bootstrap và màu/chế độ sáng tối hiện có. Không xây dashboard hoặc hệ thống thiết kế mới.

Bố cục:

1. Đầu trang: tiêu đề, mô tả ngắn và nút **Tạo bản sao lưu**.
2. Thẻ trạng thái: cloud đã kết nối/chưa cấu hình/lỗi; lần backup cloud thành công gần nhất; giờ/lần sao lưu tự động tiếp theo.
3. Thanh tiến trình theo giai đoạn thực: đang chờ, đang chụp dữ liệu, đang đóng gói, đang tải lên, đang kiểm tra. Không hiển thị phần trăm giả.
4. Danh sách: thời điểm, thủ công/tự động, người tạo, kích thước, trạng thái cloud/kiểm tra và thao tác.
5. Trạng thái chưa cấu hình: hướng dẫn kết nối; không trình bày như tính năng đã hoạt động. Không chạy job tự động khi thiếu cloud hoặc khóa mã hóa.

| Trạng thái | Hiển thị và thao tác |
| --- | --- |
| Chưa có backup | Hướng dẫn ngắn và nút tạo sau khi cấu hình đủ |
| Chờ xử lý | Hiển thị đang chờ worker, không ghi là đang sao lưu |
| Đang xử lý | Chỉ rõ giai đoạn; ngăn tạo job trùng |
| Thành công | Đã tải cloud và kiểm hash; được tải bản mã hóa |
| Tải cloud thất bại | Có bản local mã hóa; nút thử tải lại đúng bản đó |
| Thất bại khi chụp | Hiển thị nguyên nhân dễ hiểu; cho tạo bản mới sau khi xử lý |
| Cloud mất kết nối | Giữ lịch sử, báo chưa xác minh được; không xóa bản cũ |

Nút tải chỉ hoạt động cho artifact đã hoàn tất kiểm tra và đi qua route admin, không dùng liên kết Drive public. File tải là gói mã hóa dùng cho công cụ khôi phục, không phải ZIP đọc trực tiếp. Chưa thêm nút xóa tùy ý hoặc khôi phục đè database đang chạy trên web ở phiên bản đầu; cung cấp hướng dẫn/lệnh phục hồi an toàn.

Danh sách dùng nhãn chữ ngoài màu trạng thái, hỗ trợ bàn phím/focus/loading, dùng bảng cuộn hoặc thẻ trên màn hình nhỏ. Không hiển thị URI mật khẩu, OAuth client secret/refresh token hoặc khóa giải mã trên trang.

## 4. Backup chứa gì?

- Chỉ database MongoDB được cấu hình cho bản ứng dụng này; gồm business collections, embedded items, _counters, migrations, _snapshot_imports và _legacy_runtime_archive nếu tồn tại.
- Giữ BSON, index definitions và collection options bằng mongodump; không chuyển Decimal128/Date/ID sang JSON thường.
- Các file được ứng dụng lưu trong storage/app/private và storage/app/public, bao gồm hồ sơ hợp đồng. Giữ đường dẫn relative, kích thước và SHA256; không đi theo symlink/junction ra ngoài roots được cấu hình.
- Manifest: format version, backup ID, instance ID, source database, thời điểm chụp, commit/schema version, phiên bản MongoDB/Database Tools, danh sách collection/count/options/index cần kiểm, hash archive và từng file.
- File .env, API key, APP_KEY, log, vendor/node_modules, data directory mongod, cache PHP và thư mục staging backup không nằm trong gói.
- Các runtime collection sessions, password_reset_tokens, cache, cache_locks, jobs, job_batches và failed_jobs được loại khỏi dump sử dụng phục hồi để không kích hoạt lại session/OTP/job cũ. Việc loại trừ được ghi trong manifest. Archive lịch sử _legacy_runtime_archive vẫn được giữ vì là dữ liệu nguồn đã lưu.

Phiên bản đầu hỗ trợ đúng topology đang có: một ứng dụng trên một host với MongoDB replica set. Không tự mở rộng sang sharded cluster hoặc nhiều web server.

## 5. Luồng tạo và tính nhất quán

```text
Admin / lịch mỗi ngày
  -> tạo run ID và job duy nhất
  -> preflight: công cụ, cloud/key, database, disk, quyền file
  -> chặn nhận thao tác ghi mới, chờ thao tác hiện có kết thúc
  -> mongodump đúng database + copy upload + lập manifest
  -> mở lại thao tác ghi ngay sau khi capture hoàn tất
  -> đóng gói + mã hóa streaming
  -> upload Drive private + đọc lại stream kiểm SHA256
  -> ghi commit marker / completed
  -> dọn staging và áp dụng retention
```

Một khóa tiến trình bảo đảm chỉ một lần backup toàn ứng dụng chạy đồng thời, kể cả lịch và nút thủ công. Khóa không được dựa vào cache MongoDB vì MongoDB chính là tài nguyên đang backup.

Cửa sổ capture dùng write gate dùng chung trên host. Các request có khả năng thay business data hoặc upload giữ shared gate tới khi hoàn tất (kể cả cleanup khi lỗi); backup chỉ capture sau khi lấy được exclusive gate. Queue business job và lệnh CLI ghi dữ liệu cũng phải đi qua gate. Entry point phải được kiểm kê trước khi viết code: HTTP, queue before/after/exception, create-admin, import/migrate và các lệnh ghi khác. Lệnh điều phối queue/scheduler không giữ khóa suốt vòng đời làm deadlock; job backup không lấy shared gate của chính nó. Có timeout drain và release trong finally/OS khi process kết thúc.

Trong thời gian capture, thao tác ghi mới nhận phản hồi tạm bận, đọc thông tin backup vẫn được phép. Không giữ chặn ghi trong toàn bộ thời gian upload cloud. Nút backup được giải thích trước rằng hệ thống có thể tạm khóa thao tác ghi trong giai đoạn chụp; không hứa backup hoàn toàn không gián đoạn.

Không chỉ gọi artisan down rồi giả định mọi tác vụ đã dừng: request/transaction đang chạy và queue phải được drain. Nếu không lấy được gate/không bảo đảm capture nhất quán thì backup thất bại; không xuất file mang nhãn thành công. Ngoài cửa sổ ứng dụng được quản lý, không được dùng Compass/mongosh hoặc tiến trình khác ghi trực tiếp vào database/tệp trong lúc capture. File lock một host không bảo vệ writer ở host khác; cấu hình multi-host không thuộc phiên bản đầu.

Sử dụng mongodump có --db với tên database cấu hình, --archive và --gzip; loại các runtime collection nêu trên. Không dùng --oplog kết hợp --db, và không dump toàn instance để lấy oplog. Tài liệu MongoDB xác nhận --oplog yêu cầu full dump; vì vậy hướng này chọn cửa sổ chặn ghi có drain cho đúng phạm vi database.

Công cụ hiện có: mongodump/mongorestore 100.18.0; PHP có mongodb, zip và sodium. Command được chạy bằng argv array qua process API, không ghép shell text. URI chứa secret đi qua config tạm được bảo vệ, không lộ trong log/argv; xóa config tạm sau dùng.

## 6. Gói backup, mã hóa và kiểm tra cloud

Gói trong sau giải mã gồm database.archive.gz, files/private, files/public và manifest.json. Gói được mã hóa streaming bằng sodium secretstream XChaCha20-Poly1305 (PHP đã có sodium), thành file .cmcbackup. Không tự chế thuật toán hoặc nạp toàn bộ archive vào RAM.

Dùng BACKUP_ENCRYPTION_KEY riêng với APP_KEY; chỉ lưu fingerprint/key ID trong metadata. Khóa không được nằm trong backup, GitHub hoặc cùng object cloud. Người dùng cần giữ một bản khóa phục hồi ở nơi an toàn riêng; mất khóa thì file cloud không thể giải mã. Rotation phải giữ được khóa cũ cho những backup còn lưu.

Tên file do server sinh theo ngày/UUID; Drive file ID và folder ID chỉ lấy từ metadata nội bộ. Dùng appProperties ghi instance ID, backup ID và loại artifact; lọc theo đúng folder/owner marker khi list/retention. Không chấp nhận file ID/path tùy ý từ request. Kiểm identity của folder và chặn folder bị chuyển vào thùng rác hoặc chia sẻ công khai trước upload. File staging là private, nằm ngoài public/upload roots; được Git ignore. Không tạo permission anyone hoặc link chia sẻ công khai; route tải admin stream file qua server.

Upload xong phải đọc lại stream cloud để đối chiếu kích thước/SHA256 ciphertext. Không dùng ETag hoặc MD5 thay SHA256. Dùng resumable upload, stream theo chunk là bội số 256 KiB, timeout/retry hữu hạn và xử lý đúng offset phía server; URI phiên upload là secret. Lưu file ID bền vững trước retry, đối chiếu appProperties và xử lý timeout sau khi upload xong để tránh tạo bản trùng. Chỉ sau bước này mới xuất bản metadata hoàn tất. Verify đã giải mã còn kiểm authentication tag, hash archive/file và cấu trúc manifest.

Metadata nhỏ cần cho danh sách và khôi phục gồm ID, thời điểm, kích thước, trạng thái, hash ciphertext và key ID; không chứa secrets/nội dung row. Metadata local được ghi atomic dưới staging root; bản cloud sidecar/commit marker chỉ tạo sau khi object được kiểm. Khi mất máy có thể liệt kê marker trong folder của instance bằng cùng OAuth client/tài khoản còn quyền drive.file để tìm lại backup thay vì phụ thuộc duy nhất database đã hỏng.

Cloud gián đoạn: giữ artifact local đã mã hóa và trạng thái upload_failed, cho retry cùng ID mà không chụp lại dữ liệu. Không xóa bản local này theo chính sách giữ tối đa2 completed; báo dung lượng khi cần. Process chết: lần tiếp theo đánh dấu run dở thành interrupted theo khóa/heartbeat, không giả định completed và không tự xóa backup tốt.

## 7. Lịch, queue và lưu giữ

- Giờ đề xuất: 20:00, timezone Asia/Ho_Chi_Minh (UTC+7, khớp app). Không dùng UTC mặc định của host để tính lịch.
- Laravel scheduler được OS gọi mỗi phút. Trên Windows dùng Task Scheduler chạy wrapper PHP ẩn; trên Linux dùng cron/systemd. Không dựa vào việc đang mở trình duyệt.
- Logic đến hạn kiểm theo ngày: sau20:00, nếu ngày hiện tại chưa có lần tự động được nhận thì dispatch tối đa một lần. Khi máy khởi động trễ có thể chạy bù cho ngày hiện tại; không tạo hàng chục backup cho các ngày đã tắt máy.
- Queue backup tách khỏi queue nghiệp vụ, có retry_after lớn hơn giới hạn chạy backup; giá trị đề xuất 1.800s so với deadline1.200s. Job tạo backup không bị worker xử lý trùng sau90s như cấu hình queue mặc định hiện tại. Timeout process/gate được kiểm riêng cho Windows, không chỉ dựa vào pcntl của worker.
- Retry mạng có giới hạn cho cùng artifact; không retry mù toàn chu trình tạo backup để sinh nhiều object hoặc làm khóa ghi kéo dài.
- Giữ 7 bản cloud verified gần nhất theo thời điểm capture (không theo thời điểm retry upload). Chỉ retention sau khi có backup mới verified; không xóa bản cuối/tất cả bản cũ khi lần mới lỗi. Xóa vĩnh viễn chỉ artifact/marker do module tạo, đúng instance và folder, ghi audit; không chỉ đưa vào thùng rác vì file trong thùng rác vẫn tính dung lượng. Không xóa backup mới nhất/ngoài phạm vi khi metadata thiếu hoặc không rõ. Nếu xóa một cặp artifact/marker lỗi giữa chừng, lần chạy sau nhận diện và tiếp tục an toàn; không coi marker mồ côi là bản phục hồi được.
- Giữ local tối đa2 completed encrypted artifacts để tải nhanh; staging plaintext/config secret dọn trong finally. Chưa bao gồm offsite key escrow hoặc chính sách lưu tháng/năm.
- Trang admin ghi nhận thiếu worker/scheduler, lần chạy thất bại và backup quá hạn24h. Mục tiêu mất dữ liệu tối đa khoảng24h chỉ khi máy, MongoDB, scheduler và mạng hoạt động; máy tắt nhiều ngày không thể có backup hằng ngày.

## 8. Phục hồi an toàn

Có lệnh verify và restore dùng được khi giao diện/database chính không hoạt động. Đường dẫn phục hồi tham chiếu backup ID hoặc artifact đã chọn bởi operator; không nhận file path từ người dùng web.

1. Lấy object từ cloud hoặc file local; kiểm ciphertext hash, giải mã và kiểm manifest/archive/files.
2. Kiểm phiên bản MongoDB/FCV/Database Tools và schema tương thích; từ chối bản sai ứng dụng/format.
3. mongorestore dùng nsInclude + nsFrom/nsTo vào một database mới cmc_restore_<id>; từ chối đích tồn tại, database đang chạy và admin/config/local. Không dùng --drop.
4. Giải nén upload vào thư mục phục hồi mới riêng. Kiểm entry path, kích thước, symlink/junction; không cho ZIP path traversal hoặc ghi đè upload hiện hành.
5. Đối chiếu collections/count, indexes/options, BSON types, counters >= ID lớn nhất và file SHA256; kiểm quan hệ/số dư với dữ liệu thử đã biết. Lưu report kiểm tra, đánh dấu restored-and-verified riêng với cloud-hash-verified.
6. Chuyển ứng dụng sang dữ liệu phục hồi là thao tác vận hành riêng sau khi người quản trị kiểm tra. Không tự đổi .env hoặc ghi đè database/upload thật qua nút web.

Backup có thể là bản phát sinh trong quá trình chuyển đổi; app/schema version được giữ để tìm đúng phiên bản code khi phục hồi. Session/job cũ không được replay. APP_KEY và các cấu hình triển khai được phục hồi riêng từ nơi giữ secrets của chủ dự án.

## 9. Thành phần tích hợp và cấu hình

Dùng các phần sẵn có: EnsureRole, layouts/staff.blade.php, route staff, Laravel Process/Queue/Schedule/Filesystem, native Database Tools và sodium/zip. Không thêm hệ thống phân quyền hoặc lưu trữ upload mới.

Các phần dự kiến: BackupController và view admin; BackupService điều phối capture/đóng gói/cloud/verify/restore; BackupWriteGate bảo vệ entry points; CreateBackupJob; commands create/verify/restore và setup/check; config/backup.php; GoogleDriveBackupStore dùng HTTP client và một connection queue backup riêng. Tách helper nội bộ chỉ khi logic mã hóa hoặc manifest cần test độc lập, không tạo framework backup tổng quát.

Không thêm dependency Composer/npm. Không đổi các cấu hình AWS_* hoặc filesystem upload đang có. Token, staging và metadata local đều ngoài private/public upload roots để không lọt vào gói backup.

| Biến đề xuất | Ý nghĩa / mặc định |
| --- | --- |
| schedule.json.enabled | false; cấu hình qua trang admin, chỉ bật sau cloud drill đúng tài khoản/artifact |
| BACKUP_GOOGLE_CLIENT_ID / BACKUP_GOOGLE_CLIENT_SECRET | OAuth Web client; secret chỉ ở cấu hình private |
| BACKUP_GOOGLE_REDIRECT_URI | Callback cố định, khớp cấu hình Google; localhost khi phát triển, HTTPS khi triển khai |
| BACKUP_INSTANCE_ID | Namespace riêng, không dùng chung dev/prod |
| BACKUP_ENCRYPTION_KEY | Khóa riêng 32 byte base64; không đưa lên UI/Git/Drive |
| BACKUP_AT / BACKUP_TIMEZONE | 20:00 / Asia/Ho_Chi_Minh |
| BACKUP_CLOUD_COPIES / BACKUP_LOCAL_COPIES | 7 / 2 completed; không xóa pending upload retry |
| BACKUP_STAGING_ROOT | Thư mục private ngoài public và nguồn upload, chứa metadata/token được bảo vệ |
| MONGODUMP_PATH / MONGORESTORE_PATH | Đường dẫn công cụ đã xác minh; không cho request sửa |

Folder ID, file IDs và token là trạng thái kết nối lưu private, không để người dùng web nhập đường dẫn/ID tùy ý. Trang admin hiển thị kết nối, lỗi quyền, hết dung lượng và nút kết nối/ngắt kết nối; không hiển thị secrets.

## 10. Thiết lập Google do chủ tài khoản thực hiện

Tạo Google Cloud project phục vụ OAuth/API nhưng không gắn tài khoản Billing, không kích hoạt trial; bật Drive API, cấu hình consent screen và OAuth Web client/redirect URI. Chủ tài khoản tự đăng nhập Google và cấp quyền drive.file. Không yêu cầu mật khẩu Google trong ứng dụng/chat. Không đăng ký Google One. Hướng dẫn giải thích Testing/7 ngày, publication và reconnect thay vì giả định một lần đăng nhập là không bao giờ hết hạn.

Sau kết nối, app tạo folder riêng tư, kiểm quyền và dung lượng trước backup; khi quota không đọc được thì vẫn xử lý lỗi storageQuotaExceeded minh bạch. API rate limit 403/429 retry có backoff hữu hạn, lỗi hết dung lượng/quyền không retry mù. Không yêu cầu mua thêm dung lượng để tiếp tục; có thể dọn backup trong phạm vi hoặc chuyển tài khoản sau khi người dùng chủ động chọn. Đổi tài khoản tạo namespace kết nối mới, giữ nguyên lịch sử local và file cũ, không trộn retention giữa tài khoản.

Tạo/cất khóa phục hồi riêng. Kiểm một vòng backup, download, giải mã và restore vào target riêng trước bật lịch. Khi chưa có OAuth credentials có thể kiểm UI/fake HTTP và backup/restore local; phải ghi rõ chưa kiểm Drive thật. Không dùng connector Google Drive trong cuộc chat làm kết nối lâu dài cho ứng dụng Laravel.

## 11. Điều kiện nghiệm thu

- Admin active được tạo/xem/tải; guest/role khác/admin khóa bị từ chối ở mọi endpoint. Kiểm CSRF, UUID/path traversal, không lộ secret/log raw.
- Tạo qua HTTP, CLI và scheduler dùng cùng dịch vụ; chống nhấn trùng/chạy chồng; worker chậm không tạo thêm một run khác.
- Kiểm native mongodump/mongorestore trên database test riêng: Decimal128, Date, numeric IDs, embedded items, indexes/options/counters và upload giữ nguyên. Có restore rồi chạy nghiệp vụ phát sinh ID mới.
- Kiểm concurrent writer/upload đang chạy: capture chờ hoàn tất, writer mới bị chặn; giải phóng gate khi lỗi/timeouts. Không có bản completed với database/file lệch nhau.
- Kiểm cloud timeout/credential sai/partial upload: không completed, không xóa bản cũ, retry đúng artifact; kiểm hash hoặc authentication tag sai thì từ chối restore.
- Restore từ chối database đang chạy/đích tồn tại/system databases; không ghi ngoài thư mục mới. Mọi test xóa/reset chỉ trong tên cmc_mongo_test_* hoặc target restore do test sở hữu.
- Retention giữ 7 bản verified gần nhất và chỉ xóa file/marker sở hữu đúng tài khoản/instance; skip khi chưa có bản mới verified.
- Scheduler 20:00 UTC+7, chống trùng và chạy bù ngày hiện tại khi máy bật trễ; cấu hình Windows tồn tại và được kiểm tra thực chạy.
- OAuth: từ chối state sai/hết hạn/dùng lại và callback thiếu quyền; token không lộ, refresh race được khóa, invalid_grant báo reconnect. Không giữ token trong gói backup. Kiểm quota đầy, folder mất quyền, API 429, upload bị ngắt và retry không tạo bản trùng; không xóa file ngoài phạm vi hoặc tự bật billing.
- UI responsive, trạng thái chờ/lỗi/thiếu cấu hình rõ, không hiển thị phần trăm giả; full suite hiện có vẫn đạt.
- Nghiệm thu cloud thật cần tài khoản Google/OAuth của người dùng và một backup + download + restore vào target riêng; fake storage không thay bằng chứng này.

## 12. Tự rà soát thiết kế và trạng thái

Các điểm đã chốt trong đề xuất: database-only dump không dùng --oplog; có drain writer và file; mã hóa trước upload; cloud readback hash trước completed; metadata cloud độc lập DB; queue timeout không dùng90s mặc định; bắt kịp lịch khi mở máy trễ; restore không đè dữ liệu hiện hành; secret/key tách riêng; không push.

Đã thực hiện code, task Windows/worker, khóa local, full backup dữ liệu hiện có và restore vào target riêng. Chưa tạo project/OAuth client, chưa kết nối tài khoản hoặc upload Drive thật. Kết quả test/bằng chứng và giới hạn hiện tại ở backup-runbook.md.

Người dùng đã duyệt Google Drive/full backup và kế hoạch triển khai. Còn cần chủ tài khoản cung cấp cấu hình OAuth và tự cấp quyền Google để nghiệm thu cloud. Việc duyệt thiết kế không phải quyền push GitHub hoặc tự tạo dịch vụ có phí.

## 13. Nguồn đối chiếu

Đối chiếu tài liệu chính thức ngày 21/10/2026:

- [Dung lượng Google và khi đầy](https://support.google.com/drive/answer/6374270?hl=vi).
- [Drive API scopes](https://developers.google.com/workspace/drive/api/guides/api-specific-auth) — drive.file giới hạn theo tệp.
- [OAuth web server](https://developers.google.com/identity/protocols/oauth2/web-server) — offline, state và refresh.
- [OAuth token expiration](https://developers.google.com/identity/protocols/oauth2) — giới hạn Testing 7 ngày.
- [Drive shared drives overview](https://developers.google.com/workspace/drive/api/guides/about-shareddrives) — service account không có storage quota.
- [Drive resumable upload](https://developers.google.com/workspace/drive/api/guides/manage-uploads).
- [Drive API usage limits](https://developers.google.com/workspace/drive/api/guides/limits).
- [mongodump](https://www.mongodb.com/docs/database-tools/mongodump/) — BSON/options/indexes, phạm vi db và giới hạn oplog.
- [mongorestore](https://www.mongodb.com/docs/database-tools/mongorestore/) — namespace và tương thích phiên bản.
- Code đã đọc: routes/web.php, routes/console.php, layouts/staff.blade.php, EnsureRole, config/filesystems.php, config/queue.php, Mongo/Connection, StaffController upload và migration-runbook hiện hành.
