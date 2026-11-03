# Sài Gòn CMC — ứng dụng Laravel + MongoDB

Đây là ứng dụng trong bản MongoDB độc lập, giữ quy trình và bảy vai trò từ bản Laravel/MySQL gốc. Bắt đầu tại [README gốc](../README.md) để cài PHP extension local, chạy replica set và chọn import dữ liệu thật hoặc seed database demo mới.

## Lệnh thường dùng

Chạy từ thư mục `laravel/` sau khi setup môi trường theo README gốc:

```powershell
..\tools\php-mongodb.cmd artisan package:discover --no-interaction
..\tools\php-mongodb.cmd artisan migrate --no-interaction
Set-Location public
..\..\tools\php-mongodb.cmd -S 127.0.0.1:8011 ..\vendor\laravel\framework\src\Illuminate\Foundation\resources\server.php
```

`migrate` tạo schema BSON và index MongoDB; SQL migration cũ chỉ được lưu làm nguồn lịch sử. Không chạy `migrate:fresh` hoặc seed trên database đã chuyển dữ liệu. Tạo tài khoản quản trị mới bằng `..\tools\php-mongodb.cmd artisan cmms:create-admin admin`, nhập tên và mật khẩu ẩn khi được hỏi; không có mật khẩu mặc định.

```powershell
..\tools\php-mongodb.cmd vendor/bin/phpunit
```

Bộ test chỉ reset database có tên `cmc_mongo_test_*`; không dùng database nghiệp vụ cho test. Dùng wrapper cho từng PHP command vì extension local không tự đi theo các PHP process con. Trên Linux/CI đã bật MongoDB extension trong PHP, dùng `php` thay wrapper.

## Quy trình hiện có

Khách đăng ký, xem sản phẩm, lập giỏ yêu cầu báo giá, chấp nhận báo giá, xem hợp đồng và đơn hàng. Kinh doanh gửi báo giá và lập hợp đồng. Quản lý duyệt hợp đồng, danh mục, giá và đề xuất nhập hàng. Kế toán ghi nhận thanh toán, phát lệnh xuất kho và theo dõi công nợ. Kho nhận nhiều đợt, nhập/xuất theo chứng từ, kiểm kê và xử lý trả/đổi hàng. Giao nhận bố trí chuyến và ghi từng biên bản bàn giao. Báo cáo giữ metadata duyệt và snapshot để xuất; định mức phục vụ dự toán. Groq là tùy chọn khi cấu hình API key riêng.

Các tài khoản, trạng thái và password hash hiện có được giữ nguyên khi import snapshot; không công bố mật khẩu hoặc coi tên tài khoản thử của máy nguồn là dữ liệu mặc định của repo. Phiên đăng nhập, reset token và job cũ được lưu archive thay vì kích hoạt lại.

## Dữ liệu và giới hạn

Xem [thiết kế vật lý](../docs/mongodb/physical-design.md) và [runbook](../docs/mongodb/migration-runbook.md) cho ID số, Decimal128, items nhúng, upload riêng tư và kiểm chứng snapshot. Chứng từ tải lên không đồng nghĩa chữ ký điện tử hay hóa đơn điện tử. Giá/định mức seed phục vụ minh họa; đối chiếu nghiệp vụ trước triển khai thực tế. Replica set local một node phục vụ phát triển; triển khai thực tế cần xác thực, TLS và vận hành replica set phù hợp.
