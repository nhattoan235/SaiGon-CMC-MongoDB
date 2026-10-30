# Chuyển đổi MySQL → MongoDB

Hoàn tất kiểm tra sau chuyển đổi: 2026-10-30 (Asia/Saigon).

## Hệ thống đang chạy

- Website: http://localhost:8000.
- Driver vận hành: mongodb; database: cmms_mongodb.
- MongoDB 8.0 replica set cmc-rs; cổng database không được công khai.
- app-mongodb, queue-worker, rag-worker và scheduler dùng cùng database MongoDB.
- App MySQL và database MySQL của dự án đã dừng. Volume và bản sao lưu MySQL được giữ để phục hồi.
- Nhánh Git: migration/mongodb. Chưa push thay đổi lên GitHub.

## Phần triển khai

- app/Database/MongoConnection.php: transaction MongoDB snapshot/majority, thử lại xung đột qua driver, ID số, Decimal128, kiểm tra tham chiếu trước commit và callback sau commit.
- app/Database/MongoQuery.php: thực thi truy vấn Laravel bằng aggregation MongoDB, gồm lookup, nhóm/tổng hợp, lọc, sắp xếp, phân trang và CRUD. Không chạy SQL để xử lý các truy vấn này.
- app/Database/MongoSchema.php: metadata phục vụ phân quyền, kiểm tra lịch sử tài khoản và sao lưu.
- app/Database/MongoQueue.php và MongoQueueConnector.php: nhận và xác nhận job bằng MongoDB, không thăm dò PDO.
- database/mongodb-schema.json: cấu trúc 52 bảng gốc được ánh xạ sang collection, giữ ID và quan hệ hiện có.
- cmms:mongo-install: cài collection, validator, unique/index, dữ liệu tìm kiếm và bộ đếm mà không xóa dữ liệu.
- cmms:mongo-rehearse: sao chép snapshot MySQL vào đích mới, đối soát số lượng/hash từng bản ghi và 111 quan hệ. Từ chối ghi đè đích đã có dữ liệu.
- cmms:mongo-export-mysql: xuất dữ liệu MongoDB mới nhất về schema MySQL trống, đối soát dữ liệu và tham chiếu để quay lui.
- Giữ nguyên APP_KEY, mật khẩu/tài khoản hiện có, URL theo ID, phân quyền và nghiệp vụ. Tài khoản QA 7 vai trò nằm trong database thử nghiệm riêng.
- Session, cache, khóa cache, hàng đợi, job lỗi, dữ liệu nghiệp vụ và sao lưu ứng dụng dùng MongoDB.
- Truy vấn mới dùng helper SQL ngoài phạm vi đã hỗ trợ phải được bổ sung MQL và kiểm thử trước khi đưa vào ứng dụng.

## Kết quả nghiệm thu

- Hồi quy trên MongoDB: 199 test đạt, 1.634 assertion. Log: qa-mongo-final.log; JUnit: qa-mongo-final.xml.
- 4 test không chạy trong lượt hồi quy: 2 kiểm tra DDL dành riêng SQL và 2 kiểm tra rehearsal bật riêng. DDL đã được kiểm tra trong baseline SQL; nhóm snapshot/restore đã chạy riêng và đạt 3 test, 15 assertion.
- Nhóm an toàn MongoDB/hàng đợi chạy riêng: 7 test đạt, 17 assertion. Bao gồm ID số, unique nullable, số thập phân, tìm kiếm không dấu, tham chiếu mồ côi, callback/rollback lồng nhau, nhận job thật và dispatch sau commit.
- Hai tiến trình tranh lượng hàng cuối cùng, chèn lỗi ghi thanh toán và chèn lỗi xuất kho: 3 test đạt, 19 assertion trên MongoDB; đã kiểm tra tương ứng trên MySQL.
- Cửa hàng và bán hàng: 45 test đạt, 447 assertion trên MongoDB.
- Đối chiếu công nợ: 11 test đạt, 60 assertion trên MongoDB.
- Trình duyệt: đăng nhập, giỏ hàng, gửi/rút báo giá, giữ lịch sử và chặn khách khỏi quản trị (403). Đăng nhập và mở trang tương ứng của đủ 7 vai trò đạt trên database QA riêng.
- Sau chuyển cổng chính, đăng nhập bằng tài khoản cũ và xem được cả lịch sử báo giá cũ lẫn báo giá QA trên localhost:8000 khi MySQL đã dừng.
- Job thực tế: worker nhận và xử lý DONE; còn 0 job, 0 job lỗi trong database QA.
- Nguồn MySQL cuối cùng: đối soát 45 bảng nghiệp vụ, không có thay đổi so với snapshot đã chuyển. Bảng session/cache/hàng đợi/token tạm thời được loại khỏi kiểm tra đóng băng nghiệp vụ.
- Snapshot native MongoDB phục hồi sang cmms_mongodb: 55 collection khớp số lượng và hash, không có sai lệch.
- Xuất ngược MongoDB về cmms_rollback_20261029: đối soát khớp 52 bảng, gồm dữ liệu QA phát sinh sau lần import ban đầu.
- Composer validate, Composer audit và Pint đã đạt; git diff --check không báo lỗi khoảng trắng.

Các nhóm test được chạy nhiều lượt để sửa khác biệt; không cộng các số test của từng nhóm vì có giao nhau.

## Khởi động và kiểm thử

Từ thư mục gốc repo:

```powershell
docker compose -p cmc-mongodb -f compose.mongodb.yaml up -d app-mongodb queue-worker rag-worker scheduler
docker compose -p cmc-mongodb -f compose.mongodb.yaml ps
```

Tạo database kiểm thử và chạy test, không dùng database vận hành:

```powershell
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps -e MONGODB_DATABASE=cmms_mongo_test app-mongodb php artisan cmms:mongo-install
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps -e MONGODB_DATABASE=cmms_mongo_test app-mongodb php artisan test --compact
```

Reset database bị giới hạn trong môi trường testing và tên cmms_mongo_test*/cmms_qa_*. Không chạy migrate SQL trên database MongoDB; các migration SQL cũ giữ vai trò lịch sử/phục hồi.

## Sao lưu và quay lui

Các bản sao ngoài Git: C:/Users/DUC_STRONG/Projects/Sai-Gon-CMC-migration-backups.

- cmms-final-before-cutover.sql: MySQL ngay trước khi chuyển cổng.
- mysql.env và before-mongodb.patch: cấu hình/khóa và thay đổi trước chuyển đổi.
- uploaded-files-before-mongodb.tar.gz: file tải lên.
- cmc-cutover-native.archive.gz: bản sao native MongoDB đã phục hồi và đối soát.
- mongodb-live-verified.png, mongo-role-*.png: ảnh nghiệm thu.
- Ứng dụng đã tạo thêm bản sao lưu mã hóa sau chuyển đổi trong storage/app/backups, bằng BackupService hiện có.

Giữ APP_KEY cùng dữ liệu mã hóa. Không commit các file env, dump hoặc mật khẩu.

Nếu cần quay lại MySQL sau khi MongoDB đã nhận dữ liệu mới:

1. Dừng app-mongodb, queue-worker, rag-worker và scheduler để đóng băng thao tác ghi.
2. Khởi động riêng db từ compose.local.yaml.
3. Tạo database mới cmms_rollback_<tên_mới>, cài schema MySQL gốc trống và cấp quyền cho tài khoản MySQL của dự án. Không dùng đích đã có dữ liệu.
4. Chạy công cụ xuất: docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm mongo-tools php artisan cmms:mongo-export-mysql cmms_rollback_<tên_mới>.
5. Chỉ khi đối soát thành công mới đặt DB_CONNECTION=mysql, DB_DATABASE bằng tên đích vừa xuất trong laravel/.env; giữ nguyên APP_KEY và cấu hình kết nối MySQL đã sao lưu.
6. Khởi động app trong compose.local.yaml và nghiệm thu lại. Giữ volume MongoDB để có thể quay lại.

Không chuyển sang database MySQL cũ mà bỏ qua bước xuất các phát sinh mới. Exporter từ chối ghi đè và kiểm tra tham chiếu trước commit.

## Phạm vi vận hành

Cấu hình này phục vụ chạy cục bộ bằng replica set một member. Giao dịch ghi được tuần tự hóa qua khóa chung trong MongoDB để giữ tính nhất quán của nghiệp vụ hiện tại; tải ghi lớn cần benchmark trước khi mở rộng. Tham chiếu được bảo vệ ở lớp truy cập ứng dụng; các thao tác ghi trực tiếp bằng Mongo shell phải dùng quy trình đối soát riêng. Groq/Ollama/Qdrant và email tiếp tục cần cấu hình dịch vụ/key như trước chuyển đổi.