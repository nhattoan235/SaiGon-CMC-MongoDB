# Sài Gòn CMC — Laravel + MongoDB

Ứng dụng PHP nằm trong thư mục `laravel/` của kho mã. Cơ sở dữ liệu vận hành là MongoDB `cmms_mongodb`. Dữ liệu seed chỉ dùng minh họa. MySQL được giữ để đối chiếu và phục hồi, không phục vụ truy vấn của ứng dụng MongoDB.

## Chạy trên máy

Yêu cầu Docker Desktop. Chạy các lệnh ở thư mục gốc repo theo [README gốc](../README.md). Với hệ thống đã cài:

```powershell
docker compose -p cmc-mongodb -f compose.mongodb.yaml up -d app-mongodb queue-worker rag-worker scheduler
```

Mở http://localhost:8000. Lệnh `cmms:create-admin` hỏi mật khẩu tương tác; không có tài khoản quản trị mặc định. Giữ nguyên APP_KEY khi dùng dữ liệu đã chuyển hoặc khôi phục bản sao lưu. Nếu chưa có Groq key, trợ lý tra cứu sản phẩm trong danh mục nội bộ; khi có key, trợ lý dùng Groq và bộ kiến thức tư vấn khách hàng.

## Tài khoản demo

Trên máy trình diễn Windows, dùng [hướng dẫn chạy demo](../DEMO-GUIDE.md). Lệnh khởi chạy tạo tài khoản demo cho cả 7 vai trò và tồn kho minh họa khi khởi tạo database riêng. Chúng được tạo bởi Seeder có trong Git; không phụ thuộc vào database QA hoặc dữ liệu database của máy phát triển.

## Quy trình hiện có

Khách đăng ký, xem 15 sản phẩm mỗi trang, lập giỏ yêu cầu báo giá, chấp nhận báo giá, xem hợp đồng và đơn hàng. Kinh doanh gửi báo giá, lập hợp đồng. Quản lý duyệt hợp đồng và đề xuất nhập hàng. Kế toán ghi nhận thanh toán, phát lệnh xuất kho và theo dõi công nợ. Kho nhập/xuất theo chứng từ, kiểm kê và xử lý trả hoặc đổi hàng bằng chuyến giao thay thế. Giao nhận ghi biên bản bàn giao. Danh mục và giá cần duyệt trước khi áp dụng. Báo cáo có duyệt, xuất CSV và bản in A4 để lưu PDF từ trình duyệt. Dự toán vật liệu dùng định mức minh họa từ bản Java; trợ lý Groq sử dụng danh mục sản phẩm hiện hành.

## Giới hạn cần hoàn thiện trước khi triển khai thực tế

- Chứng từ hợp đồng được tải lên, chưa có ký điện tử hay hóa đơn điện tử.
- Báo cáo xuất CSV và trang in để lưu PDF từ trình duyệt; chưa tạo tệp XLSX/PDF tự động.
- Định mức và giá seed là minh họa, cần kỹ sư và cửa hàng xác nhận.
- Groq chưa thể kiểm tra trực tiếp cho đến khi cấu hình API key.
- Biên bản thu hồi tại công trình, xử lý sai lệch từ nhà cung cấp, điều phối nhiều chuyến và bộ báo cáo chi tiết theo mọi tiêu chí của tài liệu cần tiếp tục đối chiếu và mở rộng.

Chạy bộ test MongoDB trên database riêng theo README gốc. Hệ thống từ chối reset dữ liệu nếu tên database không thuộc vùng kiểm thử. Hai kiểm tra DDL SQL tiếp tục chạy trên SQLite/MySQL; nghiệp vụ, transaction và kiểm tra tham chiếu được chạy trực tiếp trên MongoDB.
