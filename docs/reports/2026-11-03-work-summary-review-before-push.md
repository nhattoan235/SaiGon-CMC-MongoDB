# Báo cáo tổng hợp thay đổi — chờ duyệt trước khi push

**Ngày lập:** 03/11/2026
**Dự án:** Sài Gòn CMC — MongoDB
**Mục đích:** Tóm tắt phần đã làm để người dùng xem và duyệt trước khi đưa thay đổi lên GitHub.

## 1. Tóm tắt

Trong workspace hiện có thay đổi nền tảng MongoDB, cập nhật nghiệp vụ và giao diện, phần Dự toán nhanh, cùng các sửa lỗi đã phát hiện khi kiểm tra. Phạm vi QoL giữ các luồng nghiệp vụ đã thống nhất; Dự toán nhanh là ngoại lệ được phép mở rộng.

Đây là bản tổng hợp toàn bộ thay đổi đang có so với commit hiện tại, không chỉ riêng lỗi giao diện vừa sửa. Có cả nhiều tệp mới và tệp bị xóa; cần duyệt toàn bộ phạm vi trước khi push.

## 2. Những phần đã triển khai

### Nền tảng MongoDB và chạy cục bộ

- Cập nhật cấu hình ứng dụng Laravel, xác thực, session/cache/queue và bootstrap để vận hành với MongoDB.
- Bổ sung adapter truy vấn và kết nối MongoDB trong `laravel/app/Database/`, schema `laravel/database/mongodb-schema.json`, lệnh cài đặt/nhập/xuất/diễn tập dữ liệu và cấu hình Docker Compose.
- Điều chỉnh service nghiệp vụ và các truy vấn để dùng lớp dữ liệu hiện hành.
- Cập nhật README và cấu hình mẫu cho cách chạy ứng dụng.
- Thay một số lớp MongoDB, backup và công cụ cũ bằng cấu trúc hiện tại. Các tệp bị xóa được thống kê ở mục 7 để người duyệt chú ý.

### Nghiệp vụ, quyền và quản trị

- Bổ sung ma trận quyền theo vai trò, middleware kiểm tra quyền và lịch sử duyệt.
- Bổ sung luồng lịch thanh toán, đối soát công nợ khách hàng/nhà cung cấp và xác nhận công nợ nhà cung cấp.
- Bổ sung luồng VietQR và màn hình quản lý tài khoản nhận thanh toán.
- Cập nhật các màn hình và service liên quan đến bán hàng, kho, giao nhận, kế toán, báo cáo và quản trị.
- Cập nhật các kiểm thử nghiệp vụ tương ứng; một số bài kiểm thử MongoDB/SQL cũ được thay thế hoặc loại bỏ cùng lớp triển khai cũ.

### Giao diện QoL

- Chỉnh các trang cửa hàng và nhân viên: điều hướng, dashboard, biểu mẫu, danh sách hồ sơ, bộ lọc, trạng thái và cách hiển thị bước tiếp theo.
- Cập nhật Blade, CSS và JavaScript ở khu vực khách hàng, nhân viên, trợ lý và báo cáo; giữ quyền và luồng nghiệp vụ theo phạm vi đã chốt.
- Tạo kế hoạch QoL và kế hoạch test giao diện trong `docs/plans/`.

### Dự toán nhanh và trợ lý AI

- Loại bỏ phần nhập mô tả để AI tự điền dự toán theo yêu cầu sau khi người dùng đánh giá chức năng này không đạt.
- Dự toán còn luồng nhập tay: loại công trình, diện tích một tầng điển hình và số tầng.
- Đưa định mức dự toán sang cấu trúc có phiên bản; admin có thể nhập bộ cũ, tạo/kích hoạt phiên bản và xem lịch sử kích hoạt.
- Giữ công thức hiện hành và nhãn định mức minh họa; trang kết quả hiển thị bộ/phiên bản và nguồn.
- Cập nhật giao diện trợ lý và cấu hình Groq. Groq đã kết nối thành công theo nhật ký UI, nhưng phần truy xuất tài liệu/vector chưa sẵn sàng.

### Sửa lỗi

- Sửa lỗi trang kế toán hiển thị cú pháp Blade ở nhãn bộ lọc; xử lý thêm lỗi biên dịch trang do khối PHP một dòng.
- Sửa lỗi giao diện mobile: nút chat nổi che nút **“Tính vật liệu”**. Trên màn hình nhỏ của trang dự toán, nút chat được đưa xuống sau nội dung.
- Kiểm tra lại riêng lỗi chồng lấp trên giao diện ở kích thước **390 × 844**; nút tính vật liệu hiển thị đầy đủ.

## 3. Kết quả kiểm tra đã ghi nhận

- Ca hồi quy kế toán `test_accounting_dashboard_filters_unsettled_payables_and_orders_without_invoices`: **1 test, 9 assertions, đạt** trên database kiểm thử riêng.
- UI dự toán: loại nhà phố bê tông cốt thép, 40 m²/tầng × 2 tầng cho 5 dòng vật liệu, tổng tham khảo **52.256.000 ₫**; các giá trị diện tích/số tầng sai đã bị chặn.
- UI cửa hàng, nhập kho, tồn kho, quyền sở hữu đơn hàng, báo giá/hợp đồng, báo cáo và quản trị định mức đã được kiểm tra một phần; kết quả theo từng nhóm nằm trong `docs/plans/2026-11-03-visible-ui-test-execution.md`.
- Không coi toàn bộ bộ test giao diện là hoàn tất. Các nhóm ở mục 5 vẫn chưa chạy đủ hoặc đang bị chặn.
- Báo cáo nghiệm thu MongoDB cũ trong `.acceptance-docs/mongodb-migration-status.md` ghi nhận 199 test/1.634 assertions ở một lượt trước. Đây là kết quả trong tài liệu nghiệm thu trước đó, không phải lượt UI hiện tại và không được cộng vào kết quả UI lần này.

## 4. Dữ liệu QA còn trên ứng dụng chính

Theo nhật ký test giao diện, dữ liệu QA đã được tạo trên môi trường đang chạy `localhost:8000`:

- 12 tài khoản QA cho các vai trò; thông tin đăng nhập ở tệp bị Git ignore: `laravel/storage/app/private/qa-credentials-main-20261103.txt`.
- Bộ định mức cũ được nhập thành phiên bản 1 cho 3 nhóm công trình; giữ nhãn minh họa.
- Có hồ sơ nhập hàng `MH-2026-000001` với 20 m³; nhật ký ghi nhận kho nhận đủ 8 + 12 m³ và công nợ nhà cung cấp 1.400.000 ₫.
- Đơn `DH-2026-000001` của khách QA đã hủy trước xác nhận; chưa thanh toán.
- Báo giá `BG-2026-000001` trị giá 1.700.000 ₫ đã được chấp nhận; hợp đồng `HD-2026-000001` đã được xác nhận nội dung theo phê duyệt của người dùng. Nhật ký ghi chưa thanh toán và chưa giao hàng.
- Báo cáo `BC-000001` đã được tạo/duyệt trong ca QA.
- Key Groq được cấu hình để kiểm tra; không ghi giá trị bí mật vào báo cáo hoặc Git.

Các tài khoản và hồ sơ này là dữ liệu kiểm thử có chủ đích, không phải dữ liệu sản xuất. Không xóa chúng trong bước lập báo cáo.

## 5. Việc chưa hoàn tất / hạn chế đã biết

- Test UI tổng thể mới hoàn tất một phần. Chưa kiểm tra đủ đăng ký/OTP/reset mật khẩu, khóa/đổi vai trò, ma trận quyền, các nhánh thanh toán/giao hàng/đổi trả/đối soát, tải và mở PDF/XLSX, backup/restore, bàn phím và toàn bộ màn hình responsive.
- Trang dự toán hiện chặn gửi nếu chưa chọn loại công trình, nhưng chưa có thông báo lỗi tiếng Việt hiển thị rõ ngay dưới ô chọn.
- Trợ lý AI chưa có tài liệu tri thức được lập chỉ mục; embedding/vector search chưa kết nối nên câu trả lời có thể chuyển sang fallback.
- VietQR chưa chạy được vì chưa cấu hình tài khoản nhận.
- Định mức và giá dự toán đang mang tính minh họa; chưa được chuyên gia xây dựng xác nhận.
- Không có tuyên bố rằng toàn bộ thay đổi hiện tại đã được kiểm thử hoặc nghiệm thu hoàn chỉnh.

## 6. Git và trạng thái push

- Nhánh đang checkout: `main`, theo dõi `origin/main`; commit hiện tại là `4ba9a34` (`Add local MongoDB backup and restore workflow`).
- So với commit đó, workspace hiện có **82 tệp đã theo dõi bị sửa, 89 tệp đã theo dõi bị xóa và 142 tệp mới chưa được theo dõi** (đã tính cả báo cáo này).
- Chưa tạo commit và chưa push thay đổi.
- Một tài liệu nghiệm thu cũ ghi nhánh `migration/mongodb`; thông tin Git hiện tại xác nhận workspace đang ở `main`. Nếu bạn muốn nhánh khác, cần chọn đúng nhánh trước khi push.
- Khi được duyệt, cần chốt phạm vi staging/commit: push toàn bộ diff hiện tại sẽ bao gồm cả 89 tệp xóa và mọi tệp mới, không chỉ các sửa QoL gần đây.

## 7. Các nhóm tệp bị xóa trong diff hiện tại

Các mục xóa đáng chú ý so với commit hiện tại:

- Tài liệu cũ trong `docs/mongodb/` và kế hoạch/spec backup cũ trong `docs/superpowers/`.
- Bộ lớp adapter MongoDB cũ trong `laravel/app/Mongo/` và provider cũ; cấu trúc mới đặt trong `laravel/app/Database/`.
- Một số lệnh, service và kiểm thử backup/MongoDB/MySQL cũ; cấu hình backup và luồng hiện tại đã được thay đổi.
- Các SQL migration được chuyển khỏi `laravel/database/sql-migrations/` sang `laravel/database/migrations/` cho cấu trúc hiện tại.
- Các công cụ PowerShell cũ dưới `tools/`.

Đây là thay đổi thật trong diff, không chỉ là mục dọn dẹp tài liệu. Cần xem lại nếu muốn giữ tương thích với các script hoặc hướng dẫn cũ.

## 8. Tài liệu liên quan

- Kế hoạch QoL và Dự toán nhanh: `docs/plans/2026-11-03-qol-and-quick-estimate-plan.md`
- Phạm vi đã chốt: `docs/plans/2026-11-03-qol-scope.md`
- Kế hoạch test giao diện: `docs/plans/2026-11-03-ui-functional-test-plan.md`
- Nhật ký test giao diện: `docs/plans/2026-11-03-visible-ui-test-execution.md`

**Trạng thái:** Báo cáo và diff đang chờ người dùng duyệt. Chưa commit, chưa push.
