# Kế hoạch chạy toàn bộ kiểm thử giao diện để người dùng theo dõi

Ngày: 03/11/2026. Trạng thái: đã lập kế hoạch; lượt thực thi mới chưa bắt đầu.

**Mục tiêu:** tự chuẩn bị tài khoản/dữ liệu QA, thao tác chức năng bằng trình duyệt trong ChatGPT tại môi trường hiện tại người dùng đang mở, lưu kết quả có bằng chứng theo từng ca.

**Căn cứ:** danh sách ca A–L và inventory trong [kế hoạch chi tiết](2026-11-03-ui-functional-test-plan.md), route và permission của đúng checkout đang chạy. Tài liệu đính kèm chỉ là nguồn đối chiếu nghiệp vụ.

**Cách chạy:** một lượt theo thứ tự dưới đây; không dùng kiểm thử tự động, gọi API trực tiếp hoặc sửa DB để thay cho thao tác người dùng trong ca UI. Công cụ dòng lệnh chỉ phục vụ dựng môi trường, khởi động dịch vụ và bootstrap ban đầu. Không sửa luật duyệt để vượt ca bị chặn.

## 1. Chuẩn bị trước lượt chạy

1. Ghi phiên bản Git, thời điểm và timezone Asia/Bangkok.
2. Dùng trang hiện tại `http://localhost:8000` và database đang cấu hình cho ứng dụng. Không dựng database khác thay cho đích test.
3. Tạo các tài khoản và dữ liệu có tiền tố `QA-UI-20261103` qua giao diện quản trị khi có thể; không sao chép thông tin cá nhân thật. Ghi rõ mọi hồ sơ kiểm thử được tạo để nhận biết.
4. Dùng tài khoản admin hiện có để tạo các vai trò QA; mật khẩu riêng, lưu ngoài Git và không đưa vào ảnh bằng chứng. Nếu không vào được quản trị, ghi Blocked, không ghi trực tiếp vào database để giả lập ca UI.
5. Kiểm tra app/worker và dịch vụ cần cho từng ca. Healthcheck chỉ xác nhận dịch vụ chạy, không tính là Pass UI.
6. Mở URL QA trong trình duyệt **bên trong ChatGPT và bật hiển thị**, giữ tab để người dùng theo dõi. Không điều khiển Edge, desktop hoặc ứng dụng khác.
6. Không chạy restore, không xác nhận chuyển tiền thật và không xóa dữ liệu hiện có trên môi trường này. Kiểm tra quy trình restore dừng ở xem/kiểm tra backup; restore cần môi trường dùng một lần.
7. Trước mỗi đợt thông báo ngắn: vai trò, chức năng và kết quả dự kiến. Sau đợt báo Pass/Fail/Blocked, lỗi phát hiện và đợt kế tiếp.

## 2. Bộ tài khoản sẽ tự tạo

Tên hiển thị bắt đầu bằng `QA UI`. Theo xác nhận của người dùng, các tài khoản được tạo trên database của môi trường hiện tại (`cmms_mongodb`) để thao tác trực tiếp qua giao diện.

| Vai trò | Username | Dùng để kiểm tra |
| --- | --- | --- |
| Khách A/B | `qa_ui_c1`, `qa_ui_c2` | Hai chủ sở hữu; giỏ/báo giá/đơn/hợp đồng/công nợ; cấm xem chéo |
| Kinh doanh S1/S2 | `qa_ui_s1`, `qa_ui_s2` | Lập/gửi báo giá, xác nhận đơn, hợp đồng, phân biệt người lập |
| Quản lý M1/M2 | `qa_ui_m1`, `qa_ui_m2` | Duyệt/từ chối, hạn mức, tự duyệt sai quyền và người duyệt khác |
| Kho W1 | `qa_ui_w1` | Mua/nhập/xuất/kiểm kê/đổi trả |
| Kế toán A1 | `qa_ui_a1` | Thu/chi, lịch thanh toán, ghi có, VietQR, đối chiếu |
| Giao nhận D1 | `qa_ui_d1` | Chuyến giao, bàn giao, giao lại và thu hồi |
| Admin AD1/AD2 | `qa_ui_ad1`, `qa_ui_ad2` | Tài khoản/quyền, tri thức/AI, ngân hàng, backup và tách người lập/duyệt |
| Tài khoản rỗng | `qa_ui_empty` | Đổi vai trò, khóa/mở khóa khi không có lịch sử |

Tổng cộng 12 tài khoản cho 7 vai trò. Tạo thêm user chỉ khi một ca đòi dữ liệu độc lập, ghi rõ lý do. Dùng tên miền `example.test` cho danh tính giả; chỉ gửi OTP/reset vào hộp thư QA đã cấu hình, không gửi email tới người thật.

Trong IAB, đăng xuất rồi đăng nhập tuần tự theo từng vai trò, xác nhận tên người đang đăng nhập trên UI trước mỗi bước. Nhiều tab chung phiên không tính là nhiều người. Ca đồng thời chỉ chạy khi có hai phiên trình duyệt độc lập bên trong ChatGPT; nếu công cụ không hỗ trợ, ghi Blocked cho đúng ca đồng thời, vẫn chạy các ca tuần tự.

## 3. Dữ liệu mẫu và kiểm chứng

- Hai kho, hai NCC, hai khách; vật liệu nguyên/lẻ, còn hàng/hết hàng/ngừng bán, có/không mua nhận tại kho. Tạo qua UI tương ứng; ghi mã hồ sơ thực tế.
- Dùng dữ liệu và phép tính chuẩn tại mục 2 kế hoạch chi tiết. Trước mỗi chuỗi ghi tồn và số dư thực tế trên UI; không tái dùng baseline của chuỗi đã phát sinh.
- Chuỗi giao công trình: 10 đơn vị × 100.000 = 1.000.000; thu 300.000 + 200.000; còn nợ 500.000; xuất kho giảm đúng 10.
- Nhận tại kho: 2 × 100.000 = 200.000; thu 100.000 phải còn bị chặn xuất, thu đủ mới bàn giao; tồn giảm 2, không tạo chuyến giao công trình.
- Mua hàng: nhận 8 rồi 12 trên phiếu 20 × 70.000; tồn và phải trả tăng theo thực nhận, tổng cuối 1.400.000.
- Trả hàng chưa thu đủ và đã thu đủ dùng các đơn riêng; đối chiếu giảm nợ, ghi có, hoàn/cấn trừ đúng một lần.
- Chuẩn bị file hợp lệ, sai loại, rỗng và vượt giới hạn từ form thực tế: hợp đồng, Markdown, chứng từ NCC, backup. Không suy đoán giới hạn khi code/form đã thay đổi.

## 4. Thứ tự thực thi

| Đợt | Chức năng và ca chi tiết | Dấu hiệu hoàn thành |
| --- | --- | --- |
| 0 | Môi trường, tài khoản, dữ liệu và mở trình duyệt hiển thị | Đăng nhập được từng vai trò; ghi baseline và URL QA |
| 1 | Tài khoản/quyền: đăng ký, OTP, login/logout, reset, khóa/mở, đổi vai trò; AUTH | Đúng vai trò truy cập được, sai vai trò và chưa đăng nhập bị chặn; kiểm cả URL trực tiếp |
| 2 | Menu, trang chủ, tìm/lọc/phân trang, sản phẩm, giỏ, hồ sơ khách; STORE | Dữ liệu/đơn vị/giá khớp; lỗi cạnh ô, giữ dữ liệu; giỏ rỗng và hết hàng có hướng dẫn |
| 3 | Chuỗi giao công trình: khách → kinh doanh → quản lý → khách → kế toán → kho → giao nhận | Báo giá/hợp đồng/lịch trả/thu/xuất/giao/hóa đơn nối đúng hồ sơ; SALE, CONTRACT, PAY, STOCK, DEL |
| 4 | Nhận tại kho, hủy/rút/từ chối, thiếu tiền/thiếu tồn, VietQR | Đúng nhánh và điều kiện chặn; gửi lặp không ghi tiền/xuất kho hai lần |
| 5 | Đề xuất mua, duyệt, nhận từng phần, kiểm kê, sức chứa/chuyến/giao thất bại/giao lại | Tồn theo kho và chứng từ khớp; chưa duyệt/chưa đủ điều kiện không làm tăng giảm tồn |
| 6 | Trả/đổi khách, thu hồi, kiểm hàng, thiếu hàng thay, trả NCC, ghi có/hoàn/cấn trừ; RETURN, SUP | Đúng từng thời điểm tiền/hàng; bản cũ giữ lịch sử; không tạo tác động trùng |
| 7 | Công nợ khách/NCC, đối chiếu, lịch trả, hạn mức; DEBT | Số dư khớp chứng từ UI; khách A không thấy B; dữ liệu chốt kỳ và quyền phản hồi đúng |
| 8 | Quản lý danh mục/mặt hàng/NCC/kho/tài khoản/ma trận/lịch sử duyệt; ADMIN | Đề xuất, duyệt/từ chối, người lập/người duyệt và các thay đổi hiển thị đúng |
| 9 | Báo cáo: lọc, tạo, duyệt, xem, in, PDF/XLSX; REPORT | Nội dung file mở được và khớp kỳ, bộ lọc, tổng tiền/số lượng của dữ liệu QA |
| 10 | Trợ lý vật liệu/chat nổi, cấu hình Groq, tài liệu/tri thức/index/nguồn; AI | Giá/tồn có căn cứ, nguồn công bố đúng quyền, lỗi dịch vụ có phản hồi và đường thao tác tiếp |
| 11 | Dự toán thủ công và quản trị định mức còn tồn tại | Chọn loại/diện tích/tầng, tính theo bộ đang áp dụng, quyền/phiên bản/nguồn; UI không còn ô/nút AI nhập mô tả |
| 12 | Desktop/mobile 390×844, theme, bàn phím, thông báo, bảng dài, trạng thái rỗng/lỗi; UX | Không tràn/mất nút chính, có focus/nhãn, đọc được trạng thái trên cả hai chế độ |
| 13 | Backup/upload/tải/restore và smoke lại tất cả vai trò; BACKUP | Chạy cuối trên QA dùng một lần; số dư/tồn/file trước backup phục hồi đúng |

Ở mỗi nhóm chạy trường hợp thành công, dữ liệu sai/biên, trạng thái không cho phép, thiếu quyền và gửi lại theo ca áp dụng. Không dùng tài khoản admin làm thay kinh doanh/kho/kế toán/giao nhận.

## 5. Nhật ký và sửa lỗi

- Mỗi ca có Run ID, Case ID/biến thể, vai trò, bước UI, mã hồ sơ, kỳ vọng, thực tế, tiền/tồn trước-sau, trạng thái và bằng chứng.
- Trạng thái gồm Not run / Pass / Fail / Blocked / Retest pass. Chỉ Pass khi thấy kết quả cuối trên UI; toast thành công không đủ.
- Lưu ảnh tại các mốc quan trọng, file PDF/XLSX đã tải và đối chiếu nội dung. Ảnh không chứa key/mật khẩu.
- Sai quyền, sai tiền, sai tồn hoặc chặn chuỗi cốt lõi: dừng ca phụ thuộc, ghi lỗi tái hiện; sửa trong phạm vi được phép rồi chạy lại ca lỗi và các bước liên quan. Không che lỗi bằng cách sửa DB.
- Thiếu email/vector/worker hoặc phiên độc lập: ghi Blocked đúng ca, tiếp tục phần không phụ thuộc. Không báo toàn bộ đợt Pass khi còn ca bị chặn.
- Khi tab hiện tại không điều khiển được, thử kết nối lại trong ChatGPT. Nếu vẫn lỗi, ghi blocker công cụ; không chuyển sang điều khiển ứng dụng ngoài ChatGPT hay tính HTTP test thay cho UI.

## 6. Điều kiện kết thúc và kết quả bàn giao

- Đã mở tất cả màn hình/action trong inventory bản code hiện tại và gắn ca tương ứng; mỗi ca A–L/biến thể có trạng thái.
- Các chuỗi cốt lõi và P0 đều Pass; chưa có bằng chứng thì giữ Not run/Blocked.
- Bàn giao bảng kết quả, danh sách lỗi và ca retest, danh sách ca chưa chạy/bị chặn, ảnh/file bằng chứng; giữ tài khoản QA cho người dùng xem lại.
- Không đưa số test tự động hoặc biên bản từ bản RAR vào số Pass của lượt UI này.
- Ước lượng ban đầu: chuẩn bị nửa ngày; các luồng nghiệp vụ 1,5–2 ngày; quyền, AI, file, responsive và restore khoảng 1 ngày. Đây là khối lượng dự kiến, không phải cam kết thời gian hay kết quả đã hoàn tất.

## 7. Trạng thái lượt thực thi

Run ID: `UI-20261103-01`. Cập nhật trong ngày 03/11/2026 (Asia/Bangkok), code `4ba9a34`. Đích UI: `http://localhost:8000`, database `cmms_mongodb`; trình duyệt ChatGPT in-app đang mở và hiển thị cho người dùng.

| Hạng mục | Trạng thái |
| --- | --- |
| Môi trường sau khi khởi động lại máy | Pass; app và MongoDB đã được khởi động lại trên `:8000` |
| Tài khoản QA | Đã tạo đủ 12 qua giao diện; thông tin đăng nhập lưu trong file bị Git ignore: `laravel/storage/app/private/qa-credentials-main-20261103.txt` |
| Tab ChatGPT in-app | Pass; trang đang mở trực tiếp trên ứng dụng chính |
| Môi trường `cmms_qa_ui_20261103_full` dựng nhầm trước đó | Đã dừng, không dùng để tính kết quả UI |
| MongoDB kiểm thử hồi quy `cmms_qa_ui_regression_20261103` | Chỉ dùng cho một test hồi quy tự động tách biệt; không thay thế ca UI và không chứa hồ sơ của môi trường chính |

### 7.1 Kết quả đã kiểm tra qua giao diện

| Nhóm | Kết quả và bằng chứng |
| --- | --- |
| Dự toán công khai | Pass một luồng hợp lệ và biên nhập liệu. Nhà phố BTCT, 40 m²/tầng × 2 tầng cho 5 dòng, tổng 52.256.000 ₫; từng dòng cộng khớp tổng. Thiếu diện tích, diện tích 0, số tầng 0 bị chặn; chưa thấy thông báo chữ cho trường chưa chọn loại công trình. |
| Responsive và giao diện | 390 × 844: biểu mẫu xếp dọc và chữ trong trường không tràn; phát hiện nút chat nổi che một phần góc phải nút “Tính vật liệu” ở màn đầu (cần xử lý). Chế độ tối bật/tắt được và đã trả về giao diện sáng. Bảng kết quả di động, bàn phím và trạng thái khác chưa kiểm tra đủ. |
| Cửa hàng và chủ sở hữu | Pass một phần: danh mục/tìm kiếm; khách C1 gửi yêu cầu lấy hàng dù tồn bằng 0 và màn hình báo thiếu hàng; Kinh doanh không thể xác nhận đơn thiếu tồn. C1 hủy `DH-2026-000001` trước khi xác nhận; UI chuyển sang “Đã hủy”, đã thanh toán 0 ₫. C2 không thấy đơn C1; C2 truy cập URL đơn nội bộ nhận 403. |
| Nhập hàng và tồn | Pass: `MH-2026-000001`, 20 m³ × 70.000 ₫; quản lý M1 duyệt, kho W1 nhận 8 rồi 12. Tồn kho và lịch sử tăng đúng 20; công nợ NCC tăng theo thực nhận thành 1.400.000 ₫. Kiểm kê số thực tế bằng số sổ không tạo điều chỉnh. |
| Báo giá → đơn → hợp đồng | Pass đến bước xác nhận nội dung hợp đồng: `BG-2026-000001` điều chỉnh còn 1.700.000 ₫, quản lý M1 duyệt; C2 chấp nhận, S1 xác nhận đơn, tạo `HD-2026-000001`, M1 duyệt, C2 xác nhận nội dung. Hiện chưa ghi nhận thanh toán và chưa giao hàng. |
| Kế toán và báo cáo | Pass một phần: tổng phải trả 1.400.000 ₫ khớp 840.000 ₫ + 560.000 ₫; báo cáo `BC-000001` được tạo/duyệt, trang in khớp 0 khoản đã trả và 1.400.000 ₫ phải trả. Tải/mở nội dung XLSX/PDF chưa xác minh. VietQR bị chặn đúng vì chưa có tài khoản nhận đã cấu hình. |
| Quyền vai trò | Pass một phần: đã đăng nhập Customer, Sales, Manager, Warehouse, Accounting, Delivery và Admin; URL sales orders và trang quản trị định mức đều bị chặn 403 với Customer. Chưa chạy toàn bộ ma trận sai quyền/đổi vai trò/khóa tài khoản. |
| Trợ lý AI | Groq kết nối thành công với `openai/gpt-oss-20b`. Khối truy xuất cho biết chưa có tài liệu, embedding chưa sẵn sàng và vector search chưa kết nối; câu hỏi vật liệu không tìm được căn cứ, chatbot trả lời fallback. Nhóm tài liệu/index/nguồn đang Blocked bởi hạ tầng và dữ liệu tri thức. |
| Quản trị định mức | Admin nhập 3 nhóm định mức cũ thành phiên bản 1 đang áp dụng; nhãn “minh họa” được giữ. Dự toán Nhà phố BTCT 40 × 2 sau nhập vẫn là 52.256.000 ₫, giống kết quả đã ghi nhận trước đó. |
| Lỗi tìm thấy và sửa | Nhãn lọc công nợ lộ cú pháp Blade; đã tách biến nhãn/chi tiết và xử lý thêm khối PHP một dòng làm trang lỗi 500 khi biên dịch. UI hiện hiển thị `Đang lọc: khoản chưa tất toán · phải trả`. Ca hồi quy `test_accounting_dashboard_filters_unsettled_payables_and_orders_without_invoices` PASS: 1 test, 9 assertions trên DB kiểm thử riêng `cmms_qa_ui_regression_20261103`. |

### 7.2 Chưa chạy hoặc đang chặn

- Chưa xác nhận thanh toán cho đơn/hợp đồng, chưa tạo chuyến giao cho `DH-2026-000002`; không có tiền thật chuyển. Luồng dừng trước các hành động tài chính và ký bản cuối.
- Chưa chạy đầy đủ đăng ký/OTP/reset, đổi vai trò/khóa user, huỷ/đổi/trả khách, trả NCC, đối chiếu công nợ, hoàn/cấn trừ, sức chứa/giao lại, toàn bộ VietQR, ma trận quyền, uploads, backup/restore, PDF/XLSX đã tải, và kiểm tra responsive/bàn phím/theme.
- Nhánh nhận tại kho, xuất kho giao công trình, thu tiền theo nhiều đợt, giao nhận và hóa đơn chưa hoàn tất.
- Không duyệt/tải tài liệu AI lên kho khách hàng; khi chưa có nguồn tri thức và dịch vụ embedding/vector, câu trả lời có căn cứ chưa thể Pass.
- Sao lưu: đã mở UI, thấy 0 bản sao lưu; chưa tạo/tải/khôi phục vì gói sao lưu sẽ chứa cả DB hiện tại, tệp hợp đồng/tải lên và cấu hình AI. Chưa có bộ dữ liệu dùng một lần riêng để rehearsal restore; không khôi phục vào DB chính.

Các trạng thái trên là kết quả một phần; không kết luận toàn bộ bộ ca đã Pass.
