# Rà soát luồng VietQR

## Luồng hiện đang có trong mã nguồn

1. **Admin** vào cấu hình tài khoản VietQR và lưu BIN, tên ngân hàng, số tài khoản, tên chủ tài khoản. Trang admin có hiển thị QR chung được dựng từ thông tin tài khoản lúc mở trang, nhưng không lưu ảnh/mã QR riêng trong hệ thống.
2. **Kế toán** vào `/staff/vietqr`, chọn một khoản phải thu, tài khoản nhận và số tiền, rồi tạo một yêu cầu QR riêng cho khoản đó. Hệ thống đóng dấu nội dung chuyển khoản dạng `CMC...` và QR điền sẵn số tiền/nội dung.
3. **Khách hàng** vào mục “Thanh toán chuyển khoản” trong menu tài khoản/chân trang. Trang chỉ hiện QR nếu kế toán đã tạo yêu cầu cho khách đó; không có yêu cầu thì trang báo kế toán cần tạo mã.
4. Khách bấm “Tôi đã chuyển khoản” (mã giao dịch phía khách là tùy chọn). Yêu cầu chuyển sang chờ xác nhận; thao tác này chưa ghi thanh toán vào công nợ.
5. **Kế toán** đối chiếu tiền vào ngân hàng, nhập mã giao dịch sao kê rồi xác nhận. Lúc này hệ thống mới ghi thanh toán và cập nhật công nợ. Kế toán cũng có thể hủy yêu cầu đang chờ.

## Chỗ khác với ý muốn ban đầu

- QR chung admin nhìn thấy không được dùng ở trang thanh toán của khách; trang khách dựng QR riêng từ yêu cầu mà kế toán đã tạo.
- Khách không tự chọn “Chuyển khoản” từ một đơn hàng để mở QR chung; khách phải vào trang chuyển khoản riêng và đợi kế toán lập yêu cầu theo công nợ.
- Tạo yêu cầu từng khoản, số tiền/nội dung riêng, báo đã chuyển, hủy yêu cầu là các bước bổ sung so với luồng dùng chung mà bạn mô tả.
- Quyền hiện tại cho **kế toán** tạo, xác nhận và hủy yêu cầu. Admin có quyền cấu hình tài khoản, nhưng bị loại khỏi quyền tạo/xác nhận VietQR.

## Luồng mong muốn

1. **Quản trị viên** cấu hình tài khoản nhận tiền và tạo/lưu một mã VietQR dùng chung cho tài khoản đó.
2. **Khách hàng** vào thanh toán, chọn **Chuyển khoản**, rồi thấy mã QR đã cấu hình cùng thông tin tài khoản nhận tiền để thực hiện chuyển khoản.
3. Sau khi khách chuyển tiền, **kế toán hoặc quản trị viên** đối chiếu giao dịch thực nhận và xác nhận thanh toán trong hệ thống.

## Nguyên tắc

- Dùng lại mã QR tài khoản do quản trị viên cấu hình; không bắt kế toán tạo một yêu cầu QR riêng cho từng khoản công nợ.
- Giữ bước xác nhận tiền đã vào tài khoản để cập nhật thanh toán/công nợ chính xác.
- Giao diện chỉ cần hướng khách chuyển khoản và cho bên phụ trách xác nhận; bỏ các bước phụ không cần thiết trong luồng hiện tại.

Luồng mong muốn chưa được triển khai. Rà soát này dựa trên controller, route, view và workflow; chưa thao tác thử toàn bộ luồng trên trình duyệt hoặc tài khoản ngân hàng thật.
