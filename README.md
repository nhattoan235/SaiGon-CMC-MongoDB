# Sài Gòn CMC — Quản lý vật liệu xây dựng

Ứng dụng chính trong repo này là **Laravel 13 + MongoDB 8** tại [`laravel/`](laravel/). Hệ thống có cửa hàng cho khách, bảng làm việc theo vai trò, quy trình báo giá → hợp đồng → thanh toán → xuất kho → giao nhận, đổi trả và báo cáo. Trợ lý Groq là tính năng tùy chọn khi có API key.

## Giao diện

| Trang chủ | Danh mục vật liệu |
| --- | --- |
| ![Trang chủ Sài Gòn CMC](docs/screenshots/trang-chu.png) | ![Danh mục vật liệu](docs/screenshots/san-pham.png) |

![Chi tiết sản phẩm gạch tuynel](docs/screenshots/chi-tiet-san-pham.png)

Ảnh được chụp trực tiếp từ ứng dụng Laravel đang chạy ở kích thước 1440 × 900 px.

## Chạy ứng dụng Laravel

Cần Docker Desktop đang chạy. Từ thư mục gốc repo, khi cài mới:

```powershell
Copy-Item laravel/.env.example laravel/.env
docker compose -p cmc-mongodb -f compose.mongodb.yaml up -d mongo mongo-init
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps app-mongodb composer install --no-interaction
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps app-mongodb php artisan key:generate --no-interaction
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps app-mongodb php artisan cmms:mongo-install --no-interaction
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps app-mongodb php artisan db:seed --force --no-interaction
docker compose -p cmc-mongodb -f compose.mongodb.yaml up -d app-mongodb queue-worker rag-worker scheduler
```

Với bản đã có dữ liệu, giữ nguyên `.env`, `APP_KEY` và volume MongoDB; không tạo lại khóa. Schema và index được cài bằng `cmms:mongo-install`; các migration SQL cũ được giữ để đọc lịch sử và phục hồi MySQL.

```powershell
docker compose -p cmc-mongodb -f compose.mongodb.yaml exec app-mongodb php artisan cmms:create-admin admin
```

Mở [http://localhost:8000](http://localhost:8000). Lệnh tạo quản trị viên hỏi mật khẩu tương tác. Điền `GROQ_API_KEY` trong `.env` nếu dùng trợ lý AI. Không đưa `.env` lên Git. MongoDB chạy replica set để hỗ trợ transaction; cổng database không được công khai ra máy chủ.

Xem [hướng dẫn Laravel chi tiết](laravel/README.md) và [hồ sơ chuyển đổi](.acceptance-docs/mongodb-migration-status.md). Seeder không tạo sẵn tài khoản hay tồn kho đầu kỳ.

## Cấu trúc repo

Xem [AI_context](AI_context/README.md) để tìm nhanh đúng luồng nghiệp vụ, file cần sửa và tình trạng kiểm thử hiện tại.

| Thư mục | Nội dung |
| --- | --- |
| [`laravel/`](laravel/) | Ứng dụng PHP/Laravel + MongoDB |
| [`customer-prototype/`](customer-prototype/) | Bản mẫu giao diện khách hàng trước khi tích hợp Laravel |

## Kiểm tra

```powershell
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps -e MONGODB_DATABASE=cmms_mongo_test app-mongodb php artisan cmms:mongo-install
docker compose -p cmc-mongodb -f compose.mongodb.yaml run --rm --no-deps -e MONGODB_DATABASE=cmms_mongo_test app-mongodb php artisan test --compact
```

Ảnh minh họa và dữ liệu seed phục vụ đồ án; cần xác nhận giá, tồn kho và định mức trước khi sử dụng thực tế.
