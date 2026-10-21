# MongoDB Google Drive Backup Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Trang admin sao lưu full database và upload, mã hóa, lưu Google Drive, có lịch và phục hồi kiểm chứng được.

**Architecture:** Metadata/token/khóa tiến trình ở private local ngoài database và upload roots. Capture dùng write gate một host; release trước đóng gói/mã hóa/upload. Drive OAuth scope drive.file, mỗi artifact độc lập, cloud readback trước completed, restore chỉ vào target mới.

**Tech Stack:** PHP 8.3, Laravel 13, mongodb/laravel-mongodb 5.11, mongodump/mongorestore 100.18, ZipArchive, sodium secretstream, Laravel HTTP/Process/Queue/Schedule; không thêm dependency.

**Spec:** ../specs/2026-10-21-mongodb-backup-design.md (bao gồm mục 2.1 chiến lược full và phục hồi).

## Global Constraints

- Chỉ sửa Sai-Gon-CMC-Laravel-MongoDB; không sửa repo SQL, không push GitHub.
- Không gắn thẻ, Billing, Google One hoặc trial; chủ tài khoản tự đăng nhập/cấp quyền Google.
- Không lộ .env, khóa, OAuth token, URI database trong Git, UI, log hoặc backup.
- Full dữ liệu bền vững và upload; loại runtime collections theo spec. Không dùng --oplog với --db.
- Chỉ một host; tạm ngăn ghi khi capture, không chặn ghi trong lúc upload. Writer ngoài app không thuộc bảo đảm.
- Giờ mặc định 20:00 Asia/Ho_Chi_Minh, có thể đổi HH:mm; cloud 7 bản verified, local 2 completed.
- Không tự bật backup trước khi kiểm cấu hình, kết nối và phục hồi thử. Khi máy tắt không có backup; chỉ chạy bù hiện tại.
- Restore không --drop, không target đang dùng/system/đã tồn tại, không đổi .env hoặc tự cutover.
- Test xóa/reset chỉ DB cmc_mongo_test_* hoặc restore target do test sở hữu; không reset cmc_mongodb.
- Không kiểm thử upload Drive thật khi chưa có credentials do người dùng thiết lập; fake HTTP không được gọi nghiệm thu cloud.

## Review Focus

- Upload hợp đồng chưa xong khi capture: giữ shared gate bao trọn file và database, exclusive capture chờ writer kết thúc.
- Worker chết/khởi động lại: giải phóng OS lock, run dở interrupted; không đánh dấu completed hoặc dispatch trùng.
- Google nhận file nhưng response timeout: retry phải nhận diện cùng ID/metadata, không sinh bản mới hoặc xóa bản tốt.
- Đổi ngày/đổi tài khoản/đổi giờ: không tính bản chụp cũ là backup mới, không retention lẫn tài khoản/instance.
- OAuth callback giả, ZIP path traversal và artifact sửa nội dung: từ chối trước thay đổi trạng thái/ghi file/restore.

## Task 1: Private metadata, configuration, encryption and safe archive

**Files:** Create laravel/config/backup.php; laravel/app/Services/Backup/BackupRepository.php, BackupArchive.php; laravel/tests/Unit/BackupArchiveTest.php, BackupRepositoryTest.php. Modify laravel/.env.example, .gitignore.

**Interfaces:** BackupRepository::create(string $source, ?int $userId): array; find(string $id): array; update(string $id, array $changes): array; all(): array. BackupArchive::pack(string $captureRoot, string $destination): void; encrypt(string $source, string $destination): void; unpackVerified(string $artifact, string $destination): array. Metadata UUID, source, capture_at, state, size, sha256, key_id, cloud IDs, retry_at và sanitized error; ghi atomic/lock. Token path nằm ngoài capture roots.

- [ ] Viết test: round-trip giữ file/hash; tamper/truncate/wrong key phải throw; ../ và symlink/junction entry bị từ chối; UUID sai không đọc ngoài root; cập nhật metadata atomic không mất run khác.
- [ ] Chạy qua tools/php-mongodb.cmd laravel/vendor/bin/phpunit từ laravel với từng file test (dùng đường dẫn ../tools/php-mongodb.cmd và vendor/bin/phpunit); xác nhận RED đúng nguyên nhân.
- [ ] Cài minimal API trên với sodium secretstream framing/final tag, chunk bounded, manifest format/version và giới hạn extraction; file tạm private, cleanup finally, tuyệt đối không include staging/token/config.
- [ ] Chạy lại hai test file, xác nhận GREEN. Không thêm crypto tự chế.

## Task 2: Write gate across HTTP, Artisan and queue

**Files:** Create laravel/app/Services/Backup/BackupWriteGate.php, laravel/app/Http/Middleware/ProtectBackupCapture.php; modify laravel/bootstrap/app.php, laravel/app/Providers/AppServiceProvider.php; test laravel/tests/Feature/BackupWriteGateTest.php.

**Interfaces:** BackupWriteGate::shared(callable $callback): mixed; exclusive(callable $callback, int $timeoutSeconds = 30): mixed. Request shared gate bao trọn nghiệp vụ/upload; đăng ký CLI và queue lifecycle, hỗ trợ nesting một process và release khi exception. Capture orchestrator không tự giữ shared gate.

- [ ] Kiểm kê HTTP GET có side effects nghiệp vụ, HTTP ghi, import snapshot, create-admin, migrate/seed và queue jobs. Lập allowlist commands chỉ đọc/orchestrator; unknown CLI writer bảo vệ theo mặc định. Không giữ lock xuyên queue:work hoặc schedule:work; mỗi child/job được bảo vệ.
- [ ] Viết test hai process: exclusive đợi writer đã vào; writer mới nhận busy trong capture; ngoại lệ và process chết nhả lock; retry backup không deadlock; quyền admin vẫn kiểm trước hành động backup.
- [ ] Chạy test thấy RED, triển khai flock shared/exclusive nonblocking với deadline và owner-safe reentrancy; callback/gate không dùng cache MongoDB.
- [ ] Chạy GREEN; ghi giới hạn single host và direct mongosh writer.

## Task 3: Native full capture and isolated restore

**Files:** Create laravel/app/Services/Backup/MongoBackup.php; laravel/app/Console/Commands/BackupVerify.php, BackupRestore.php; tests laravel/tests/Feature/MongoBackupRestoreTest.php.

**Interfaces:** MongoBackup::capture(string $destination): array; verify(string $artifact): array; restore(string $artifact, string $targetDatabase, string $targetFiles): array. Manifest gồm capture_at, source database, versions, collections/count/index/options, archive/file SHA256 và exclusions.

- [ ] Viết integration test DB riêng với Decimal128, UTCDateTime, embedded items, counters/indexes/validators, file hợp đồng và record xóa/cập nhật. Restore phải giữ dữ liệu/hash; tạo ID tiếp theo không trùng. Live/existing/system target, sai format/key/path bị từ chối.
- [ ] Chạy RED, rồi dùng argv Process API gọi mongodump --db/--archive/--gzip và exclude runtime. Chỉ count/manifest/copy file dưới exclusive gate; URI credential qua config tạm bảo vệ; không log raw stderr chứa secret.
- [ ] Restore xác minh artifact trước ghi, target DB/files chưa tồn tại, namespace remap không --drop; kiểm counts/types/indexes/options/counters/files và tạo report. Database chính hỏng vẫn dùng được CLI với local artifact/key riêng.
- [ ] Chạy GREEN và đo capture/restore; ghi bằng chứng test, không công bố RTO chưa đo hoặc sửa business data hiện tại.

## Task 4: Google OAuth and Drive streaming storage

**Files:** Create laravel/app/Services/Backup/GoogleDriveBackupStore.php, GoogleDriveConnection.php; laravel/app/Http/Controllers/BackupGoogleController.php; tests laravel/tests/Feature/BackupGoogleConnectionTest.php, laravel/tests/Unit/GoogleDriveBackupStoreTest.php; modify laravel/routes/web.php.

**Interfaces:** GoogleDriveConnection::authorizationUrl(): string; callback(string $code, string $state): void; disconnect(): void; accessToken(): string. Store::upload(array $run, string $artifact): array; download(array $run, string $destination): void; completed(): array; delete(array $run): void. Chỉ IDs nội bộ đúng account/folder/instance.

- [ ] Viết Http::fake tests callback state sai/hết hạn/replay/không đủ quyền; refresh token không bị mất khi response thiếu, refresh race, invalid_grant reconnect, revoke không xóa backup.
- [ ] Viết tests resumable offset/chunk/timeout sau commit không duplicate; 403 quota, 429 backoff bounded; folder trashed/public, file khác instance, marker mồ côi, stream hash sai không completed.
- [ ] Chạy RED, triển khai REST OAuth/Drive bằng HTTP client sẵn có, scope drive.file/offline, token mã hóa Laravel Crypt local; callback fixed redirect, state one-time gắn session/admin. Folder do app tạo, không share public.
- [ ] Upload chunk bội 256 KiB, bounded retries/timeouts, file ID ổn định; kiểm readback SHA256 ciphertext trước marker. Pagination đầy đủ, scoped list/delete; không dùng download URL do request cung cấp.
- [ ] Chạy GREEN; ghi Testing token 7 ngày và reconnect. Không tạo project hoặc upload thật trong task test.

## Task 5: Orchestration, dedicated queue, retry and retention

**Files:** Create laravel/app/Services/Backup/BackupService.php, laravel/app/Jobs/CreateBackupJob.php; laravel/app/Console/Commands/BackupCreate.php, BackupCheck.php; modify laravel/config/queue.php; test laravel/tests/Feature/BackupLifecycleTest.php.

**Interfaces:** BackupService::request(string $source, ?int $userId): array; run(string $id): void; retryUpload(string $id): void; status(): array. One run lock xuyên capture/upload, bounded deadline 1200s; backup MongoDB queue retry_after 1800s, queue backup, không chiếm default worker.

- [ ] Viết tests manual/scheduled race chỉ nhận một run; state queued/capturing/packing/uploading/verifying/completed, upload_failed giữ encrypted local, capture lỗi dọn plaintext/nhả gate, stale run interrupted.
- [ ] Viết tests chỉ completed sau readback; retry cùng artifact sau 5/15/30 phút kể từ lỗi đầu, tối đa 3 tự động; quota/invalid_grant ngừng tự retry; capture lỗi báo lỗi không retry mù toàn pipeline.
- [ ] Viết tests giữ 7 verified cloud theo capture_at, 2 completed local; pending-upload không bị xóa; retention chỉ sau new verified và không chạm account/instance khác; xử lý partial pair deletion idempotent.
- [ ] Chạy RED, triển khai pipeline/preflight/cleanup/đồng bộ metadata và cloud marker. Worker start kiểm phục hồi run dở khi không có OS lock owner, không đánh dấu interrupted cho tác vụ thật đang chạy.
- [ ] Chạy GREEN; kiểm dedicated worker cấu hình đúng và lỗi sanitized.

## Task 6: Daily schedule and Windows runner

**Files:** Create laravel/app/Services/Backup/BackupSchedule.php; laravel/app/Console/Commands/BackupTick.php; tools/backup-local.ps1; modify laravel/routes/console.php; tests laravel/tests/Unit/BackupScheduleTest.php.

**Interfaces:** BackupSchedule::settings(): array; save(bool $enabled, string $time): void; tick(): void. File settings private ghi atomic; HH:mm, Asia/Ho_Chi_Minh; mặc định 20:00, disabled. Tick theo phút gọi service request; không giữ shared gate orchestrator.

- [ ] Viết clock tests trước/sau 20:00, UTC khác local, start muộn chạy bù ngày hiện tại, nhiều ngày tắt không backfill, manual verified sau due thay auto, old capture upload hôm nay không hoàn tất lịch hôm nay, queued/running không dispatch trùng, đổi giờ không tạo spam.
- [ ] Viết tests upload retry chưa đến hạn không chạy, exhausted/quota không lặp theo phút, thiếu cấu hình không queue, tác vụ stale có thể được xử lý có giới hạn thay vì retry vô hạn.
- [ ] Chạy RED, triển khai due logic và đăng ký Laravel schedule mỗi phút. OS task dùng đúng wrapper PHP/workdir; worker backup riêng, hidden, kiểm executable/PID/path trước Stop; không kill PHP khác.
- [ ] Chạy GREEN; script Status/Start/Stop kiểm scheduler heartbeat và worker. Chỉ bật daily settings sau Drive/restore drill đạt; máy tắt hiển thị giới hạn thực tế.

## Task 7: Admin page, protected actions and setup guide

**Files:** Create laravel/app/Http/Controllers/BackupController.php, laravel/resources/views/staff/backups.blade.php; modify laravel/routes/web.php, laravel/resources/views/layouts/staff.blade.php, laravel/resources/views/staff/nav-icon.blade.php; tests laravel/tests/Feature/BackupAdminTest.php; docs/mongodb/backup-runbook.md.

**Interfaces:** Named routes staff.backups.index, create, retry, download, settings; OAuth connect/callback/disconnect admin active. Form settings enable/time; metadata statuses không expose tokens. Download stream encrypted verified artifact qua server, không public URL.

- [ ] Viết permissions tests guest, mọi role khác/admin khóa bị chặn ở tất cả endpoint; CSRF, UUID/file ID injection; connect callback vẫn kiểm quyền. HTTP create trả nhanh với queued, double-click không duplicate.
- [ ] Viết tests missing config, reconnect, quota, worker stale, last cloud capture age, disabled schedule, manual/auto source và tiếng Việt errors; UI không có fake percentage hoặc nút restore đè live.
- [ ] Chạy RED, triển khai bằng layout staff/Bootstrap/DM Sans hiện có, semantic states, focus/loading/mobile; thêm sidebar admin. Update settings hỗ trợ HH:mm; bật fail khi chưa đủ điều kiện vận hành.
- [ ] Chạy GREEN; runbook hướng dẫn Cloud project không billing/trial, OAuth Web client/redirect, Testing expiry, quyền drive.file, khóa giải mã riêng, backup/restore/download, Windows scheduler và khi máy mất.

## Task 8: Local setup, verification and handoff

**Files:** Modify chỉ .env/local runtime ignored cần thiết; cập nhật docs/mongodb/backup-runbook.md với bằng chứng không chứa secret. Không stage tài liệu so sánh báo cáo đang có hoặc thay repo SQL.

- [ ] Kiểm phiên bản cài đặt, đọc AGENTS/rules và testing-best-practices nếu tìm được trước viết test; nếu skill thực sự không có thì ghi rõ và dùng quy tắc PHPUnit trong AGENTS. Tạo file PHP qua artisan make --no-interaction, format Pint modified files.
- [ ] Tạo backup key ngẫu nhiên chỉ trên máy và hướng dẫn bản key phục hồi riêng không đưa lên Drive; kiểm git ignore; không in key. Giữ BACKUP_ENABLED=false khi chưa có kết nối và drill.
- [ ] Chạy các backup test, full PHPUnit hiện có, Pint --dirty --format agent, route:list và kiểm view compile. Wrapper từ laravel: ../tools/php-mongodb.cmd vendor/bin/phpunit; không artisan test subprocess mất extension MongoDB.
- [ ] Chạy integration capture/restore trên DB/files test riêng; lưu đo size/capture/upload giả/restore và bằng chứng cleanup/concurrency. Xác nhận không thay đổi DB nghiệp vụ thật.
- [ ] Kiểm UI bằng browser đang chạy tại localhost nếu có; Start runner hidden và kiểm Status theo cấu hình, chưa bật daily auto khi chưa đủ credentials.
- [ ] Dẫn chủ tài khoản tạo/cấu hình OAuth và tự đăng nhập, không gửi secret qua chat. Nếu thiếu credentials, bàn giao setup local đã kiểm với cloud chưa nghiệm thu; không báo hoàn thành end-to-end hoặc giả cloud success.
- [ ] Khi có quyền, chạy Drive upload/readback/download rồi restore vào target riêng; kiểm đầy đủ, bật daily theo lựa chọn đã duyệt, kiểm heartbeat. Chỉ nghiệm thu cloud sau bằng chứng thật.
- [ ] Soát diff/secrets, báo kết quả tests và giới hạn; không push. Không tự cutover dữ liệu hoặc thay repo remote.

## Self-review and execution handoff

Các yêu cầu spec đã ánh xạ: archive/security (1), consistency (2), capture/restore/RPO-RTO (3), OAuth/cloud (4), retry/retention/state (5), schedule (6), UI/permissions/runbook (7), actual setup/verification (8). Năm failure modes Review Focus đều có test trong task sở hữu. Chưa có credentials Google nên cloud thật là bước người dùng phải tham gia, không cản trở xây dựng/test local sau khi kế hoạch được duyệt.

Đề xuất thực hiện trực tiếp trong phiên này (native) để giảm chi phí cho đồ án; không chia thêm agent triển khai. Cần người dùng rà soát kế hoạch trước khi viết code theo writing-plans. Việc duyệt không cấp quyền push hoặc mua dịch vụ.

## Execution status21/10/2026

- Tasks1–7: code đã triển khai, kiểm bằng PHPUnit và các test native/OS có liên quan; không thay Composer/npm dependencies.
- Task8 local: setup key/ignore/Windows ACL, full backup dữ liệu hiện tại, verify và restore vào target mới đã đạt; watcher/worker/task đăng nhập có bằng chứng thực chạy, LastTaskResult=0. Web8011 chạy local.
- Task8 cloud: còn chờ OAuth client do chủ tài khoản tạo cho project không Billing, đăng nhập/cấp quyền Drive và vòng upload/download/restore thật. Lịch vẫn tắt; không coi fake HTTP hoặc local drill là nghiệm thu cloud.
- Review độc lập2 finding đã sửa bằng regression tests; các test thêm kiểm counter, no-billing gate, exhausted retry, retention7/2, revoked token và cloud drill.
- Không commit/push tự động. Các file báo cáo đang chỉnh từ trước được giữ nguyên, repo SQL không đổi.
