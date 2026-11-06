# Hướng dẫn chạy bản demo trên máy khác

## Yêu cầu

- Máy Windows có Docker Desktop đã cài và được bật.
- Có kết nối Internet trong lần đầu chạy để tải Docker image, thư viện PHP/giao diện và mô hình AI cục bộ BGE-M3 (khoảng 1,2 GB).
- Giải nén nguyên thư mục repo rồi bấm đúp `Start-Demo.bat` ở thư mục gốc. Trình duyệt sẽ mở trang web khi khởi động xong.

Các lần chạy sau dùng lại database và mô hình trong Docker volume trên máy đó; tài khoản demo và dữ liệu mẫu không cần tạo lại. Bấm `Stop-Demo.bat` để dừng ứng dụng; dữ liệu vẫn được giữ.

## Tài khoản demo

Tất cả tài khoản dưới đây dùng mật khẩu `CmcDemo!2026`.

| Vai trò | Tên đăng nhập |
| --- | --- |
| Khách hàng | `demo_customer` |
| Kinh doanh | `demo_sales` |
| Quản lý | `demo_manager` |
| Kho | `demo_warehouse` |
| Kế toán | `demo_accounting` |
| Giao nhận | `demo_delivery` |
| Quản trị viên | `demo_admin` |

Database MongoDB, Ollama và Qdrant của bản demo được tách riêng khỏi các dự án Docker khác. Tồn kho được nạp bằng phiếu đầu kỳ minh họa; giá và định mức cần xác nhận nếu dùng cho giao dịch thật.

Groq API key không nằm trong repo hay bộ demo. Các chức năng không cần Groq vẫn dùng được; muốn bật AI Groq thì cấu hình key riêng trên máy trình diễn.

## Đưa lên Git

Seeder, file Compose và script khởi chạy được commit như mã nguồn nên người nhận kéo repo về sẽ có sẵn hướng dẫn để tự tạo database demo. Database MongoDB, `.env`, APP_KEY, API key, mô hình AI và volume Docker là dữ liệu máy cục bộ, không được đưa lên Git.
