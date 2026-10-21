# Sao lưu MongoDB vào Google Drive

Chỉ dùng cho bản MongoDB độc lập. Không push khi chưa được chủ repo đồng ý.

## Chi phí và điều kiện miễn phí

Dùng tài khoản Google cá nhân còn dung lượng. Không đăng ký Google One, không nhập thẻ, không bật free trial và không liên kết Cloud Billing cho project OAuth. Project chỉ bật Google Drive API; ứng dụng không gọi API mua gói, tăng quota, Compute, Cloud Storage hoặc billing. BACKUP_GOOGLE_NO_BILLING_CONFIRMED mặc định false: chỉ xác nhận true sau khi chủ tài khoản kiểm tra Billing của đúng project.

Google hiện cung cấp dung lượng miễn phí dùng chung Drive/Gmail/Photos (tối đa15GB theo tài khoản). Đầy thì dừng upload và báo lỗi. Drive API có quota và Google dự kiến có tính phí vượt ngưỡng sau này; không thể cam kết thay chính sách nhà cung cấp. Không liên kết Billing, không mua gói và giữ mức sử dụng thấp là điều kiện triển khai này. Nếu Console yêu cầu bật Billing/thẻ/trial, dừng setup, không đồng ý.

App giới hạn mỗi artifact512MB, tối đa500 thao tác Drive/2GB truyền tính gộp mỗi ngày theo instance local. Đây là chốt bảo vệ ứng dụng, không phải hard billing cap cho toàn bộ tài khoản/project Google; tiến trình khác có thể sử dụng quota ngoài app. Không tự nới giới hạn, retry tối đa3 lần tại phút5/15/30 kể từ lỗi đầu; hết dung lượng/mất quyền dừng retry tự động.

Nguồn chính thức đối chiếu21/10/2026: [Drive API limits/pricing](https://developers.google.com/workspace/drive/api/guides/limits), [storage quota](https://support.google.com/drive/answer/6374270?hl=vi).

## Setup local (đã có công cụ trên máy này)

Từ thư mục laravel dùng wrapper ../tools/php-mongodb.cmd; wrapper nạp MongoDB extension riêng, không đổi PHP toàn máy. Không chạy artisan test vì subprocess có thể mất extension; dùng vendor/bin/phpunit.

```powershell
..\tools\php-mongodb.cmd artisan cmms:backup-setup --export-key=D:/duong-dan-private-moi/recovery-key.txt
..\tools\php-mongodb.cmd artisan cmms:backup-check
```

Setup chỉ tạo key nếu chưa có; không tự thay key cũ. Tệp key đích phải chưa tồn tại và thư mục cha đã có. Cất một bản ở USB/nơi an toàn tách máy, không cùng folder Drive chứa backup. Mất key không giải mã được. Token Google được mã hóa bằng APP_KEY: cần giữ APP_KEY riêng nếu muốn phục hồi token; có thể cấp quyền lại Google thay vì mang token cũ.

Trên máy hiện tại: staging .runtime/backups, key export .runtime/backup-recovery-key.txt. Cả hai được Git ignore; không gửi nội dung key qua chat/Git. Không coi bản key trên cùng máy là đã có bản giữ ngoài máy.

## Kết nối Google do chủ tài khoản thực hiện

1. Mở Google Cloud Console, chọn/tạo project riêng và kiểm Billing không liên kết, không bật trial.
2. Bật Google Drive API. Google Auth Platform: cấu hình Branding/Audience, chọn tài khoản sử dụng theo mô hình cá nhân. Thêm test user nếu app đang Testing.
3. Tạo OAuth client loại Web application, redirect URI khớp chính xác BACKUP_GOOGLE_REDIRECT_URI. Local mặc định http://127.0.0.1:8011/staff/backups/google/callback; triển khai thật dùng HTTPS cố định.
4. Download JSON credentials, lưu ở .runtime/google-oauth-client.json (private, Git ignore). Không dùng service account cho My Drive cá nhân, không chia sẻ mật khẩu.
5. Import bằng lệnh sau, chỉ thêm confirm-no-billing sau khi kiểm đúng project. Không in secret hoặc dán vào chat.

```powershell
..\tools\php-mongodb.cmd artisan cmms:backup-setup --google-client=../.runtime/google-oauth-client.json --confirm-no-billing
```

6. Nếu Laravel đang cache config, chạy artisan config:clear; khởi động lại worker sau đổi cấu hình. Đăng nhập admin active, mở /staff/backups, bấm Kết nối Google Drive, tự đăng nhập/cấp quyền drive.file.
7. App tạo folder SaiGon-CMC-Backups-<instance>. Giữ folder riêng tư. Scope drive.file chỉ quản lý tệp app tạo, không xin quyền toàn Drive. Token ở .runtime/backups/google.enc, ngoài nguồn upload và ngoài artifact.
8. Google OAuth External/Testing có thể hết refresh token sau7 ngày. Xem [OAuth expiry](https://developers.google.com/identity/protocols/oauth2); cần reconnect hoặc cấu hình publishing phù hợp chính sách thực tế. Không hứa đăng nhập một lần tồn tại mãi.

## Tạo backup, trạng thái và lịch

Bấm Tạo bản sao lưu trong admin hoặc:

```powershell
..\tools\php-mongodb.cmd artisan cmms:backup-create
..\tools\php-mongodb.cmd artisan cmms:backup-check
```

Chờ worker → capture → đóng gói/mã hóa → upload → readback hash → completed. Capture tạm ngăn request nghiệp vụ, chờ writer đã vào kết thúc; nhả gate trước upload. Các entry point HTTP, CLI ghi và queue jobs được bảo vệ. Không dùng Compass/mongosh hoặc tiến trình ngoài app để ghi trong lúc capture. Tinker tương tác/direct driver cũng là writer ngoài gate tự động; một host duy nhất. Nếu cần triển khai nhiều host phải đổi cơ chế gate/backup.

Mặc định20:00 Asia/Ho_Chi_Minh, lịch tắt cho tới khi Google, khóa và phục hồi thử sẵn sàng. Bật/đổi giờ tại admin. Mỗi ngày nhận tối đa một tác vụ tự động; bật máy muộn chạy bù hôm nay, không tạo giả các ngày đã tắt. Backup manual chụp và verified sau giờ hẹn thay cho lần tự động hôm nay. Thời điểm upload không đổi capture_at. Lỗi capture/sau hết retry cần xử lý thủ công, không phát sinh job vô hạn từng phút.

Cloud giữ7 bản verified mới nhất, local giữ2 completed; pending upload được giữ để retry, không tự dọn mất artifact này. Không đủ chỗ cho bản mới thì giữ bản cũ và báo lỗi. Retention chỉ sau bản mới đã kiểm; xóa vĩnh viễn đúng file do app sở hữu (thùng rác Drive vẫn tính dung lượng), không xóa file người dùng khác. Không tự xóa backup thủ công ở UI.

## Runner Windows

```powershell
.\tools\backup-local.ps1 -Action Install
.\tools\backup-local.ps1 -Action Start
.\tools\backup-local.ps1 -Action Status
.\tools\backup-local.ps1 -Action Stop
# Chỉ khi muốn gỡ tự khởi động:
.\tools\backup-local.ps1 -Action Uninstall
```

Install tạo task đăng nhập cho đúng user hiện tại; không bật máy tắt hoặc chạy khi user đã logout. Watcher và worker chạy ẩn, tự kiểm lại theo phút, dùng đúng PHP wrapper flags. Watchdog chỉ dừng worker có executable/đường dẫn/PID thuộc project khi active backup vượt1250s; không dừng PHP khác. Queue backup retry_after1800s, worker timeout1200s; không dùng retry_after90s default. PID và log ở staging. Nếu task bị Windows từ chối quyền, phải ghi rõ chưa cài autostart; có thể Start thủ công trong phiên đăng nhập hiện tại.

## Kiểm tra và phục hồi

Full logical backup gồm database ứng dụng, BSON/types, indexes/options và file private/public. Loại sessions/cache/password_reset_tokens/jobs/job_batches/failed_jobs; giữ _legacy_runtime_archive lịch sử và _snapshot_imports. Không chứa .env, APP_KEY, backup key, OAuth credentials, vendor hoặc data directory mongod.

Tạo bản local khi chưa cấu hình Google để kiểm thử, không gọi đó là thành công cloud:

```powershell
..\tools\php-mongodb.cmd artisan cmms:backup-create --local-only --now
..\tools\php-mongodb.cmd artisan cmms:backup-verify D:/private/backup.cmcbackup
..\tools\php-mongodb.cmd artisan cmms:backup-restore D:/private/backup.cmcbackup --database=cmc_restore_drill_YYYYMMDD --files=D:/private/restore-drill-moi
```

Restore chỉ chấp nhận target cmc_restore_* mới (test có namespace riêng), không live/admin/config/local/đích đã tồn tại, không --drop. File giải nén vào thư mục mới, chặn path traversal/symlink, giới hạn2GB extraction. Kiểm authentication tag/hash, MongoDB major version, toàn bộ document BSON hash/count và index/options sau native restore. Chưa tự đổi .env/cutover; report ở thư mục phục hồi. Drill thành công ghi readiness theo key hiện tại; đổi key yêu cầu drill lại.

Khôi phục khi máy mất: lấy artifact mã hóa từ Drive bằng tài khoản chủ sở hữu, lấy key ở nơi riêng, chuẩn bị MongoDB/Database Tools tương thích, cấu hình tạm app/key/URI mới rồi chạy CLI restore. Không cần database chính hoạt động để dùng artifact local. Nếu giữ cùng OAuth client/tài khoản thì cmms:backup-check --cloud liệt kê commit markers theo folder/appProperties; nếu mất OAuth client cũ thì tải file trực tiếp bằng Drive UI. Marker chứa metadata trong description, không chứa secret hoặc dữ liệu bản ghi.

RPO mục tiêu khoảng24h chỉ khi hệ thống hoạt động; tuổi của capture cloud mới nhất quyết định cảnh báo. RTO phải đo bằng restore drill; readback hash không thay kiểm thử phục hồi. Mô hình local+Drive không được gọi đủ3-2-1 khi chưa xác nhận phương tiện và tính độc lập.

## Trạng thái nghiệm thu

Đã có test native dump/encrypt/restore trên database test riêng; OAuth/upload mô phỏng không thay kết nối thật. Chỉ đánh dấu cloud đã nghiệm thu sau khi chủ tài khoản cấp quyền, chạy upload/readback/download và restore vào target riêng thành công. Không tự bật lịch hoặc hiển thị cloud success khi chưa có bằng chứng đó.

## Bằng chứng kiểm tra local ngày21/10/2026

- Đã tạo artifact local mã hóa từ cmc_mongodb: 75.590 byte,4 tệp upload và database archive. Chưa upload cloud.
- Đã native restore vào cmc_restore_backup_check_v2_20261021 và .runtime/restore-backup-check-v2-20261021, kiểm32 collections/BSON/hash/index/options/counters; report verified=true. Phần restore+kiểm riêng mất khoảng2,25giây trong lần đo này, không gồm chuẩn bị môi trường/download/cutover và không phải cam kết RTO.
- Đã kiểm trên DB test riêng: Decimal128/UTCDateTime, validator/collation/index, embedded data, deleted record không sống lại; sau restore nghiệp vụ tạo sản phẩm nhận ID11 từ counter10. Counter nhỏ hơn ID có sẵn bị từ chối.
- Task SaiGon-CMC-MongoDB-Backup đã cài cho user hiện tại, Start-ScheduledTask cho LastTaskResult=0; watcher/worker chạy ẩn và heartbeat cập nhật theo phút. Đã sửa script mongosh để tương thích Windows PowerShell5.1 bằng --file thay --eval có lỗi quoting.
- Google configured=false/connected=false, chưa có OAuth client, không có tài khoản Billing nào được tạo/liên kết bởi quá trình setup. Lịch hiện enabled=false. Local drill không thay cloud drill: phải restore đúng artifact SHA256 đã completed trên cloud của tài khoản hiện tại mới bật lịch được.
- Review độc lập phát hiện2 lỗi: marker mồ côi/trùng không được tính vào retention và watchdog phải tính theo từng attempt. Cả hai có test tái hiện RED rồi GREEN. Test ngắt upload kiểm cùng phiên/offset; token phiên upload được mã hóa trong metadata private.
- Marker mồ côi/trùng bị bỏ qua khi đếm bản khôi phục được, không được dùng làm lý do xóa thêm bản tốt. Chưa tự dọn các marker mồ côi này trong lệnh liệt kê.

Kết quả kiểm thử cuối: **138/138 tests, 1.204 assertions**, Pint đã chạy và Blade compile thành công. Task đăng nhập đã được thử từ trạng thái Stop: tự tạo lại watcher/worker, LastTaskResult=0, heartbeat cập nhật. Cloud thật vẫn chưa nghiệm thu và lịch tắt.
