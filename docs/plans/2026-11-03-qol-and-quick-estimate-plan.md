# Customer and Staff QoL + Quick Estimate Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Khách hàng và nhân viên nhìn vào màn hình biết trạng thái, việc cần làm và thao tác tiếp theo; giữ nghiệp vụ trong Word, ngoại lệ là Dự toán nhanh.

**Architecture:** Tận dụng Laravel Blade, các route, permission, trạng thái và dữ liệu hiện có. Tổ chức lại menu, danh sách và biểu mẫu theo nhiệm vụ; chỉ Dự toán nhanh được đề xuất thay đổi mô hình dữ liệu và bổ sung AI. Triển khai từng chức năng sau khi chốt phạm vi, không gộp tất cả màn hình trong một lần sửa.

**Tech Stack:** Laravel 13, PHP 8.3, MongoDB qua adapter hiện có, Blade, Bootstrap và CSS/JavaScript đang có; tích hợp Groq hiện có cho phần AI được thống nhất.

---

## Trạng thái và ràng buộc

- Đây là bảng kế hoạch theo yêu cầu ngày 03/11/2026. Người dùng đã yêu cầu triển khai toàn bộ kế hoạch.
- Tiến độ hiện tại: mục 01–11 đã được triển khai; mục 12 đã triển khai luồng quản trị phiên bản định mức và đang kiểm tra hồi quy; mục 13 mới kiểm tra một số ca giao diện và chưa nghiệm thu toàn bộ luồng theo vai trò.
- Mục 12 chốt nhập diện tích một tầng điển hình, giữ nguyên engine tính và nhãn minh họa cho định mức seed. AI gợi ý ba trường có allowlist server. Admin được nhập định mức cũ thành bản 1, tạo và kích hoạt phiên bản trực tiếp, xem lịch sử và kích hoạt lại; các phiên bản bất biến, không có bước duyệt.
- Các kiểm thử hồi quy đã chạy cho những phần đã sửa; chi tiết UI thực sự đã quan sát và các giới hạn nghiệm thu được ghi trong `2026-11-03-ui-functional-test-plan.md`.
- Hướng chung đã thống nhất: QoL, giữ nghiệp vụ Word, Dự toán nhanh là ngoại lệ, bàn xong từng chức năng trước khi chuyển tiếp.
- Chi tiết mỗi hàng dưới đây là đề xuất cần chốt lần lượt, không mặc định mọi ý đã được duyệt.
- Nguồn đối chiếu: `D:/Learning/NoSQL/SaiGon-CMC-MongoDB/.acceptance-docs/Nhom3.doc` và bản trích `Nhom3-normalized.txt`. Nội dung tài liệu được dùng làm yêu cầu tham chiếu.
- Ngoài Dự toán nhanh: giữ vai trò, quyền, điều kiện duyệt, trạng thái, chứng từ, cách tính tiền và tồn kho; ưu tiên không đổi bảng/trường/quan hệ DB.
- Luồng mua nhận tại kho và luồng báo giá/hợp đồng phải được thể hiện đúng nhánh hiện có; không ép mọi đơn vào cùng một chuỗi bước.
- Không chuyển tất cả hằng số sang DB. Không thêm AI tra cứu đơn riêng tư hoặc tạo/sửa/hủy giao dịch.
- Repo đang có nhiều thay đổi từ bản thay thế Projects.rar. Khi triển khai phải ghi nhận diff nền, giữ các thay đổi đó và chỉ đánh giá phần sửa của từng mục.

## Bảng kế hoạch

| Thứ tự | Hạng mục / người dùng | Nội dung sửa dự kiến | Tiêu chí hoàn thành | Tác động nghiệp vụ |
| --- | --- | --- | --- | --- |
| 01 | Menu và lối vào cho khách | Sửa nhãn gây hiểu nhầm; làm rõ Sản phẩm, Dự toán nhanh, Hồ sơ mua hàng; đưa các lối vào báo giá/hợp đồng/đơn/thanh toán về nhóm dễ tìm | Từ trang bất kỳ khách tìm được chức năng cần dùng, biết mục đang mở; các đường dẫn hiện có vẫn truy cập được | QoL, giữ quyền và route nghiệp vụ |
| 02 | Danh mục và chi tiết sản phẩm | Tìm/lọc/sắp xếp dễ hiểu; danh mục hoạt động lấy từ dữ liệu hiện có; hiển thị đơn vị, giá, tồn khả dụng và cách mua đúng quyền | Khách hiểu đang mua đơn vị nào, số lượng hợp lệ và lựa chọn nào có thể thực hiện; bộ lọc giữ khi chuyển trang | QoL, dùng quy tắc và dữ liệu hiện có |
| 03 | Giỏ yêu cầu báo giá / mua nhận tại kho | Phân biệt rõ hai lựa chọn theo điều kiện hiện có; sửa số lượng thuận tiện; thông báo thiếu hàng và lỗi cạnh đúng dòng; giữ dữ liệu đã nhập | Khách hiểu nút nào gửi yêu cầu báo giá, nút nào đặt nhận tại kho; không nhầm thành thanh toán thành công | QoL, giữ điều kiện đặt đơn và giá/tồn |
| 04 | Hồ sơ mua hàng của khách | Làm rõ danh sách báo giá, hợp đồng, đơn, thanh toán và hồ sơ liên quan; tóm tắt trước, mở chi tiết khi cần; chỉ rõ đang chờ ai và nút tiếp theo | Khách tìm đúng hồ sơ, hiểu trạng thái và thao tác được phép theo đúng nhánh đơn hàng; giao dịch của khách khác không truy cập được | QoL, giữ trạng thái và quyền sở hữu |
| 05 | Bảng làm việc nhân viên | Tận dụng dashboard theo vai trò đang có; ưu tiên danh sách việc đang chờ; số đếm dẫn đến đúng bộ lọc; nhóm tác vụ thường dùng và hệ thống | Nhân viên nhìn thấy việc thuộc quyền mình, bấm vào mở đúng danh sách/hồ sơ; số đếm và danh sách khớp | QoL, chỉ dùng trạng thái và permission hiện có |
| 06 | Kinh doanh: báo giá, đơn, hợp đồng | Mỗi màn hình có một thao tác chính theo trạng thái; tìm hồ sơ theo mã/khách; biểu mẫu dài chia nhóm rõ nghĩa; giữ dữ liệu khi báo lỗi | Nhân viên biết đang lập, gửi hay chờ duyệt; không bỏ qua giảm giá/hợp đồng cần duyệt; từ đơn mở đúng báo giá/hợp đồng | QoL, giữ luồng bán hàng trong Word |
| 07 | Kho và giao nhận | Gom thông tin cần thao tác theo chứng từ; làm rõ nhận hàng/xuất kho/kiểm kê/đổi trả/giao hàng; hiển thị lý do chưa thể xử lý | Nhân viên chọn đúng chứng từ, vật liệu và số lượng; thấy rõ bước đang chờ và thông tin bàn giao; không sinh biến động kho ngoài thao tác hiện có | QoL, giữ điều kiện xuất/nhập/duyệt |
| 08 | Kế toán và công nợ | Tóm tắt phải thu/phải trả/đã thanh toán/còn lại; nhóm biểu mẫu theo hồ sơ; chỉ rõ số tiền, đối tượng và chứng từ trước khi ghi nhận | Không nhầm thu với chi hoặc khách với NCC; màn hình trước/sau thao tác thể hiện cùng số liệu; các bước VietQR và phát lệnh xuất vẫn đúng | QoL, giữ toàn bộ quy tắc tiền và đối soát |
| 09 | Quản lý và quản trị | Tách nhóm đề xuất cần duyệt, mặt hàng/danh mục, NCC/kho, tài khoản theo permission; mở biểu mẫu khi chọn thao tác; trình bày thay đổi rõ trước khi duyệt | Người dùng tìm đúng nhóm việc, hiểu nội dung thay đổi; vẫn không tự duyệt hồ sơ mình lập khi quy tắc hiện có cấm | QoL, giữ ma trận quyền và quy trình duyệt |
| 10 | Báo cáo | Nhãn và bộ lọc rõ ràng, giữ lựa chọn; trạng thái chờ duyệt/đã duyệt dễ hiểu; đường dẫn xem/tải nhất quán | Lọc, tạo, duyệt và xuất đúng loại báo cáo hiện có; không thay tổng số hay snapshot của báo cáo cũ | QoL, giữ loại báo cáo, quyền và bước duyệt |
| 11 | Trợ lý hiện có | Cải thiện tìm sản phẩm từ DB, câu trả lời ngắn, thẻ sản phẩm, đường dẫn chức năng; trạng thái đang trả lời/lỗi/thử lại rõ; chính sách theo nguồn hiện có | Thông tin sản phẩm khớp DB; không bịa giá/tồn/chính sách; khi AI lỗi vẫn có đường tìm sản phẩm và thao tác form bình thường | QoL; kết nối Dự toán nhanh thuộc mục 12 |
| 12 | Dự toán nhanh + AI | Loại công trình và bộ định mức từ DB; AI gợi ý thông tin nhập; admin nhập bản cũ, tạo/kích hoạt phiên bản, xem lịch sử và quay lại bản cũ; giữ engine và chuyển dòng đã chọn sang giỏ | Khách kiểm tra/sửa gợi ý; lỗi AI vẫn tính bằng form; kết quả hiện nguồn/phiên bản; admin thao tác trực tiếp; lịch sử không mất; quyền khác admin bị từ chối | Ngoại lệ cho phép mở rộng; chỉ nghiệp vụ Dự toán nhanh thay đổi |
| 13 | Nghiệm thu giao diện và hồi quy | Đi qua luồng khách và 7 vai trò trên dữ liệu QA; kiểm tra desktop/mobile, bàn phím, trường hợp rỗng/lỗi, quyền và giao dịch quan trọng | Hoàn thành các ca áp dụng, có bằng chứng và lỗi còn lại; số tiền/tồn/trạng thái/quyền khớp trước và sau sửa | Kiểm tra tác động, không thêm nghiệp vụ |

## Mỗi chức năng được thực hiện như thế nào

1. Mở màn hình hiện tại, đối chiếu ca sử dụng trong Word và xác định điểm người dùng dễ nhầm.
2. Chốt nội dung hiển thị, nút chính và các thao tác phụ; ghi rõ quyền và trạng thái sử dụng.
3. Xác định các Blade/CSS/JS cần sửa, tái sử dụng thành phần hiện có.
4. Sửa một phần nhỏ rồi kiểm tra trực tiếp trên giao diện; không tự tạo trạng thái hoặc bỏ kiểm tra phía server để làm giao diện đơn giản hơn.
5. Nếu sửa logic đọc/lọc/gửi dữ liệu, chạy hoặc bổ sung test hồi quy phù hợp; thay đổi chữ/màu/bố cục thuần túy không cần tạo test mới.
6. Kiểm tra chức năng của từng vai trò liên quan và so sánh diff; chốt kết quả trước mục tiếp theo.

## Điểm kiểm chứng khả năng sử dụng

- Người dùng mới tìm được lối vào công việc qua nhãn có nghĩa, không cần nhớ tên bảng DB hoặc mã trạng thái.
- Mỗi hồ sơ thể hiện ít nhất: mã hồ sơ, trạng thái, đang chờ ai, thao tác tiếp theo nếu có quyền.
- Nút chính dùng động từ cụ thể; biểu mẫu và thao tác phụ mở đúng lúc, có thể tìm lại được.
- Việc gom menu không làm mất quyền truy cập chức năng đang có; kiểm tra cả đường dẫn trực tiếp phía server.
- Trạng thái rỗng có hướng dẫn phù hợp; lỗi chỉ đúng ô; dữ liệu hợp lệ đã nhập được giữ lại.
- Chờ tải/gửi có phản hồi rõ; thao tác tiền/hàng tiếp tục dựa vào cơ chế chống gửi lặp hiện có.
- Cải thiện giao diện không tự động coi là dễ dùng: cần quan sát người đại diện khách/nhân viên hoàn thành nhiệm vụ mẫu và ghi điểm họ còn bị mắc.

## Các điểm cần chốt riêng trước mục 12

- Đã chốt nhập diện tích một tầng điển hình; giữ công thức hiện hành, trong đó từng định mức quy định riêng việc nhân theo số tầng.
- Đã chốt giữ nguồn định mức seed ở nhãn minh họa cho đến khi người có chuyên môn xác nhận.
- Quy đổi đơn vị, hao hụt nếu áp dụng, bước mua tối thiểu, số chữ số thập phân và giới hạn 1.000.000 của giỏ.
- Phiên bản: admin kích hoạt trực tiếp, không qua duyệt; lưu nguyên bộ, giữ bản cũ và ghi từng lần kích hoạt. Màn hình dự toán hiện không lưu kết quả thành hồ sơ; nếu bổ sung lưu dự toán/báo giá thì phải gắn phiên bản vào hồ sơ đó.
- Adapter MongoDB đang đọc `laravel/database/mongodb-schema.json`; nếu đổi cấu trúc phải có cách nâng cấp validator/index/dữ liệu hiện có, không chỉ thêm migration SQL.
- AI gợi ý loại công trình, diện tích một tầng và số tầng từ mô tả; chỉ chọn loại từ định mức đang có, hỏi lại diện tích nếu mô tả chỉ nêu tổng/không rõ cơ sở. Server giới hạn mọi giá trị; phép tính và giá vẫn thuộc hệ thống. Endpoint giới hạn 6 lần/phút, hết hạn 15 giây và lỗi vẫn để khách tự nhập.
- Quản trị/phiên bản định mức đã triển khai trong phạm vi đã chốt. Khi cập nhật database MongoDB hiện hữu cần chạy `php artisan cmms:mongo-install`; dữ liệu legacy có thể nhập ở trang quản trị mà không xóa bảng cũ.
- Tận dụng cơ chế duyệt và vai trò hiện có; không mặc định thêm vai trò kỹ sư.

## Vị trí hiện thực dự kiến

Các đường dẫn dưới đây tính từ `D:/Learning/NoSQL/SaiGon-CMC-MongoDB/`.

| Nhóm | Tệp hiện có cần xem xét | Kiểm tra phù hợp nếu thay hành vi |
| --- | --- | --- |
| Menu và cửa hàng | `laravel/resources/views/layouts/store.blade.php`, `store/home.blade.php`, `store/catalog.blade.php`, `store/product.blade.php`, `store/cart.blade.php`, `laravel/app/Http/Controllers/StoreController.php`; các đường dẫn `store/` trong cột này thuộc `laravel/resources/views/` | `StorefrontTest.php`, `CommerceWorkflowTest.php` |
| Hồ sơ khách | `laravel/resources/views/store/quotes.blade.php`, `quote.blade.php`, `contracts.blade.php`, `orders.blade.php`, `transfers.blade.php`, `profile.blade.php` trong cùng thư mục | `StorefrontTest.php`, `SalesOrderManagementTest.php`, `VietQrWorkflowTest.php` theo phần bị tác động |
| Nhân viên | `laravel/resources/views/layouts/staff.blade.php`, `laravel/resources/views/staff/dashboard.blade.php`, các partial `*-overview.blade.php`, `sales-quotes.blade.php`, `sales-orders.blade.php`, `warehouse-inventory.blade.php` | `StaffSectionTest.php`, `SalesOrderManagementTest.php`, `StocktakeWorkflowTest.php` theo phần bị tác động |
| Quản trị / báo cáo | `laravel/resources/views/staff/management.blade.php`, `reports.blade.php`, `report-print.blade.php` trong cùng thư mục; `laravel/app/Http/Controllers/ReportController.php` nếu sửa xử lý bộ lọc | `CatalogWorkflowTest.php`, `PermissionWorkflowTest.php`, `ApprovalWorkflowTest.php`, `ReportWorkflowTest.php` theo phần bị tác động |
| Trợ lý | `laravel/resources/views/store/assistant.blade.php`, `laravel/app/Http/Controllers/AssistantController.php` | `AssistantTest.php`, `ChatExperienceTest.php` |
| Dự toán | `laravel/app/Http/Controllers/EstimateController.php`, `laravel/resources/views/store/estimate.blade.php`, `laravel/database/seeders/DatabaseSeeder.php`, `laravel/database/mongodb-schema.json`; chỉ định tệp mới sau khi chốt thiết kế | `EstimateTest.php`, `StorefrontTest.php`, các ca AI và chuyển sang giỏ phù hợp |
| CSS / JS | `laravel/public/store/customer-ui.css`, `customer-ui.js`, `staff.css`, `staff-ui-tools.js`, `ui.js`, `theme.css` trong cùng thư mục | Kiểm tra trình duyệt: kích thước màn hình, focus, menu, form, trạng thái tải/lỗi |

Tất cả tệp test nêu trên nằm trong `laravel/tests/Feature/`. Chỉ chạy các ca liên quan khi sửa chức năng tương ứng; không mặc định chạy toàn bộ cho mỗi thay đổi chữ/bố cục.

Lệnh mẫu cho test nghiệp vụ có liên quan, từ thư mục gốc repo, dùng DB riêng cho test:

```powershell
docker compose -p cmc-uiqa-20261103 -f compose.mongodb.yaml run --rm --no-deps -e MONGODB_DATABASE=cmms_mongo_test app-mongodb php artisan test --compact tests/Feature/EstimateTest.php
```

Trước khi chạy xác nhận cấu hình test trỏ đúng DB riêng; không dùng DB đang cho người dùng xem để chạy test có reset dữ liệu. Kết quả mong đợi: các ca liên quan pass và thao tác UI đạt tiêu chí tương ứng. Không chạy test trong bước chỉ lập kế hoạch này.

## Liên hệ các tài liệu đã có

- Phạm vi và quyết định: `D:/Learning/NoSQL/SaiGon-CMC-MongoDB/docs/plans/2026-11-03-qol-scope.md`.
- Danh sách ca UI để chọn ca nghiệm thu phù hợp: `D:/Learning/NoSQL/SaiGon-CMC-MongoDB/docs/plans/2026-11-03-ui-functional-test-plan.md`.
- Word: ngoài Dự toán nhanh, chỉ cập nhật ảnh/chú thích giao diện khi cần; mọi phát hiện yêu cầu đổi nghiệp vụ phải được nêu riêng trước khi sửa.
