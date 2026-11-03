# VietQR dùng chung

Đã chỉnh ngày 04/11/2026:
- Admin cấu hình tài khoản nhận tiền và xem ảnh VietQR dùng chung.
- Khách mở Chuyển khoản từ đơn hàng hoặc menu để xem QR tài khoản đang hoạt động; tự nhập số tiền và mã đơn hàng.
- Kế toán ghi nhận thanh toán bằng luồng Tài chính & công nợ hiện có.
- Đã gỡ endpoint tạo yêu cầu QR, báo đã chuyển, xác nhận và hủy riêng của VietQR.
- Dữ liệu yêu cầu QR cũ và service cũ được giữ để bảo toàn lịch sử; không còn endpoint truy cập các thao tác này.

Kiểm tra: PHP lint controller/service; biên dịch Blade; đối chiếu route VietQR. Chưa thử giao dịch ngân hàng thật.
