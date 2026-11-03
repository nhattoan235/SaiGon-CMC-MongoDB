# Kế hoạch kiểm thử toàn bộ chức năng qua giao diện

> Cập nhật sau khi gỡ AI nhập mô tả: dùng `2026-11-03-visible-ui-test-execution.md` làm thứ tự chạy và chính sách tài khoản của lượt mới. Mục 10 bên dưới là nhật ký lịch sử, không phải kết quả của lượt mới. Người dùng đã cho phép tự tạo tài khoản QA cần thiết; thiếu tài khoản không còn là lý do chờ người dùng cấp sẵn. Dự toán thủ công và quản trị định mức vẫn còn; không chạy lại chức năng AI nhập mô tả đã gỡ.

Ngày lập: 03/11/2026. Đối tượng: cây dự án vừa thay từ Projects.rar trong checkout SaiGon-CMC-MongoDB, dựa trên HEAD 4ba9a34 và các thay đổi chưa commit. Trạng thái: ĐÃ THỰC HIỆN MỘT PHẦN; xem nhật ký tại mục 10.

**Mục tiêu:** kiểm tra các màn hình và thao tác hiện có, tính đúng đắn của tiền/tồn kho/công nợ qua các vai trò, quyền truy cập và khả năng hoàn thành quy trình từ đầu đến cuối.

**Cách thực hiện:** thao tác trên trình duyệt, đăng nhập từng tài khoản độc lập; đối chiếu kết quả trên màn hình và tài liệu tải xuống. Không ghi database, gọi API trực tiếp hoặc dùng lệnh để thay thế thao tác UI trong một ca test. Chuẩn bị môi trường/dữ liệu nền là công việc riêng trước khi chạy. Ca cần trạng thái không tạo được bằng giao diện phải ghi Blocked và lý do; không tự đánh dấu Pass.

**Công nghệ cần có:** Laravel 13/PHP 8.3, MongoDB replica set theo bản archive, trình duyệt, dịch vụ email thử nghiệm, Groq và Ollama/Qdrant/worker cho các ca AI tương ứng.

## 1. Điều kiện trước khi bắt đầu

- Dựng một môi trường QA riêng bằng schema của bản RAR, không trỏ vào database của bản MongoDB cũ hoặc dữ liệu vận hành. Lưu URL, phiên bản code, tên môi trường, thời gian và múi giờ Asia/Bangkok trong biên bản.
- Chuẩn bị ứng dụng, MongoDB, hàng đợi, scheduler và thư mục upload có quyền ghi. Việc cài thư viện/khởi động dịch vụ chưa nằm trong đợt lập kế hoạch này.
- Có hộp thư thử để đọc OTP/reset password. Chỉ dùng tài khoản ngân hàng và dữ liệu chuyển khoản giả lập được quy định cho QA; thao tác xác nhận VietQR kiểm tra sổ phần mềm, không chuyển tiền thật.
- Chuẩn bị tài khoản độc lập: khách A/B; kinh doanh S1/S2; quản lý M1/M2; kho W1; kế toán A1; giao nhận D1; quản trị AD1/AD2. Mật khẩu lưu riêng, không ghi vào kế hoạch hoặc ảnh bằng chứng.
- Có hồ sơ có/không có lịch sử để test đổi vai trò. Để test người lập tự duyệt, dùng AD1 lập và AD1 duyệt cùng hồ sơ, rồi AD2 duyệt; admin có nhiều quyền nhưng vẫn phải tách người lập/người duyệt.
- Tách browser profile cho từng vai trò; không dùng nhiều tab chung session để giả lập hai người khác nhau. Test hai tab cùng session được chỉ định riêng cho gửi lặp.
- Có PDF/JPG/PNG hợp đồng hợp lệ, file sai định dạng, file vượt 10 MiB; Markdown hợp lệ, rỗng, sai loại và vượt 1 MiB; backup hợp lệ/sai khóa/hỏng; chứng từ xác nhận NCC hợp lệ/sai loại theo giới hạn biểu mẫu.
- Chuẩn bị bản backup nền và bộ dữ liệu riêng cho restore. Ca restore chỉ chạy trên môi trường QA dùng một lần, sau các ca khác.

## 2. Dữ liệu và số kiểm chứng

Tạo dữ liệu qua UI khi có màn hình hỗ trợ, dùng tiền tố QA-UI-20261103 và hậu tố lần chạy. Các giá sau là giá QA, không phải giá kinh doanh.

| Mã | Dữ liệu mẫu | Mục đích |
| --- | --- | --- |
| P1 | Xi măng, đơn vị bao, giá bán 100.000 đ, mua trực tiếp được; tồn K1=100, K2=20 | Đơn giao, pickup và tính tiền nguyên |
| P2 | Cát, đơn vị m³, giá 200.000 đ; tồn K1=10,000 | Số lượng lẻ, làm tròn, thể tích |
| P3 | Vật liệu báo giá, không bật mua trực tiếp | Chặn pickup sai loại |
| P4 | Vật liệu đang ngừng bán; P5 hết tồn | Hiển thị, thiếu hàng và điều kiện đặt đơn |
| K1/K2 | Hai kho; NCC1/NCC2; hai khách A/B | Kiểm tra đúng kho, đúng đối tượng và quyền sở hữu |
| DH-GIAO | A đặt 10 P1 = 1.000.000 đ | Chuỗi bán hàng chính |
| Lịch DH-GIAO | Ký: 300.000 đ; trước xuất: 200.000 đ; sau giao 7 ngày: 500.000 đ. Hai đợt đầu bắt buộc trước xuất; cọc=500.000 đ | Phân bổ thanh toán và chặn xuất sớm |
| DH-PICKUP | B đặt 2 P1 = 200.000 đ, K1, lịch bắt buộc trước xuất đủ 200.000 đ | Bàn giao tại kho |
| MH-01 | NCC1 giao K1 20 P1 × giá mua 70.000 đ; nhận hai đợt 8 và 12 | Tồn và phải trả theo thực nhận |
| KB-QA01 | Markdown QA có thông tin duy nhất, ví dụ mã chính sách QA-CMC-ALPHA | Xác định câu trả lời có lấy đúng tài liệu và nguồn |

Sau mỗi chuỗi, ghi số đầu kỳ riêng. Không tái dùng số tồn/tiền kỳ vọng của chuỗi trước nếu đã phát sinh giao dịch khác.

Các mốc kiểm chứng bằng UI:

- DH-GIAO: tổng 1.000.000; trả 300.000 rồi 200.000 → phải thu còn 500.000, đủ các đợt trước xuất. Xuất 10 P1 từ K1: tồn 100 → 90 nếu chuỗi độc lập. Bàn giao xong: kích hoạt hạn của đợt 500.000 theo ngày bàn giao + 7 ngày.
- DH-PICKUP: trả 100.000 còn thiếu → chưa được xuất; trả thêm 100.000 → đủ điều kiện. Xuất 2: K1 100 → 98 nếu chuỗi độc lập. Không tạo chuyến giao công trình.
- MH-01: nhận 8 → tồn tăng 8, phải trả phát sinh 560.000; nhận 12 → tồn tăng tổng 20, tổng phải trả 1.400.000. Không cộng tồn/phải trả theo lượng đặt trước khi thực nhận.
- Trả hàng chưa thanh toán hết: DH-GIAO đã trả 500.000, trả lại 2 P1 trị giá 200.000 → nghĩa vụ còn 800.000, đã trả 500.000, còn phải thu 300.000, không phát sinh khoản hoàn dư. Hàng tái sử dụng được làm tồn tăng 2.
- Trả hàng đã thanh toán đủ: một đơn riêng tổng/đã trả 1.000.000, trả lại 2 P1 → nghĩa vụ 800.000, khoản ghi có 200.000. Hoàn 50.000 và cấn trừ 150.000 vào đơn khác của cùng khách → khoản ghi có hết số dư. Chỉ giảm nghĩa vụ/sinh ghi có một lần.

## 3. Quyền và phạm vi màn hình

Nguồn chuẩn để dựng kỳ vọng: laravel/routes/web.php, app/Support/PermissionMatrix.php, middleware EnsurePermission và kiểm tra bổ sung trong controller/service. Ma trận hiện tại có 7 vai trò; có màn hình xem ma trận và đổi vai trò tài khoản, chưa thấy route sửa tùy ý danh sách permission. Không lập ca yêu cầu một trình sửa quyền chưa tồn tại.

| Vai trò | Khu vực chính cần chạy | Điểm phải bị chặn tiêu biểu |
| --- | --- | --- |
| Khách | Store, giỏ, báo giá, hợp đồng, đơn, hóa đơn, VietQR, đối chiếu nợ, hồ sơ | Khu nhân viên; hồ sơ của khách khác |
| Kinh doanh | Báo giá, xác nhận đơn, hợp đồng/PDF, đề xuất danh mục, báo cáo bán hàng | Thu tiền, xuất kho, duyệt chiết khấu/hợp đồng |
| Kho | Mua/nhập, kiểm kê, xuất, chuyến, đổi trả, NCC/kho, báo cáo kho/giao nhận | Thu tiền và tự duyệt đề xuất |
| Kế toán | Thanh toán, lịch nợ, ghi có, hóa đơn, VietQR, đối chiếu nợ, xác nhận NCC | Tự xuất kho hoặc duyệt hợp đồng |
| Giao nhận | Nhận chuyến, bàn giao, giao lại, thu hồi đổi trả, báo cáo logistics | Ghi tiền, nhập/xuất và kiểm hàng trả |
| Quản lý | Duyệt và giám sát, hạn mức khách, báo cáo | Các thao tác thực thi không được cấp trong ma trận |
| Admin | Tài khoản, AI/tri thức, ngân hàng, backup và các quyền được cấp | VietQR tạo/xác nhận bị loại khỏi quyền admin mặc định; không tự duyệt hồ sơ do mình lập |

Với từng màn hình/action: chạy 1 lần đúng vai trò, 1 lần sai vai trò và 1 lần chưa đăng nhập. Với dữ liệu riêng tư: A truy cập URL chi tiết của B bằng thanh địa chỉ; cả trang lẫn file tải xuống phải bảo vệ sở hữu. Không dùng việc ẩn menu làm bằng chứng duy nhất của phân quyền.

## 4. Danh sách ca kiểm thử

Mỗi dòng là một ca; các biến thể phân cách bằng dấu chấm phẩy phải có kết quả riêng trong nhật ký. Mặc định tất cả là Not run. P0: sai tiền/tồn/quyền hoặc chặn luồng cốt lõi; P1: chức năng chính; P2: trải nghiệm/trình bày. Khi thao tác phải bị từ chối, kiểm tra lại danh sách/chi tiết để chứng minh không đổi trạng thái, tiền hoặc tồn.

### A. Tài khoản, đăng nhập và quyền

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| AUTH-01 | P1 | Đăng ký email mới; gửi OTP; điền đúng OTP và thông tin | Tạo được khách; đăng nhập và mở hồ sơ |
| AUTH-02 | P1 | OTP sai/hết hạn/tái dùng; email/username trùng; mật khẩu thiếu điều kiện | Báo đúng trường; không tạo tài khoản lỗi/trùng |
| AUTH-03 | P1 | Gửi OTP/reset liên tục tới ngưỡng cấu hình | Thông báo giới hạn rõ; có thể thử lại đúng thời gian |
| AUTH-04 | P0 | Đăng nhập đúng từng vai trò; sai mật khẩu nhiều lần; tài khoản disabled | Đúng trang/quyền; khóa theo cấu hình; disabled bị chặn |
| AUTH-05 | P1 | Quên mật khẩu; mở link hợp lệ; link sai/hết hạn/tái dùng | Reset hợp lệ; mật khẩu cũ hết tác dụng; token lỗi bị từ chối |
| AUTH-06 | P0 | Logout rồi Back/refresh/truy cập URL riêng; tài khoản bị vô hiệu hóa khi đang mở phiên | Không tiếp tục thao tác riêng tư bằng phiên không còn hợp lệ |
| AUTH-07 | P0 | Với mỗi vai trò mở trang được phép và URL trang bị cấm; thử form cũ sau khi admin đổi role | Quyền hiện hành được áp dụng; không đổi dữ liệu trái quyền |
| AUTH-08 | P0 | A mở báo giá/hóa đơn/đối chiếu/transfer hoặc nguồn tài liệu riêng của B qua URL có sẵn | Không lộ dữ liệu/tệp của B |
| AUTH-09 | P1 | Sửa hồ sơ, điện thoại/email sai hoặc trùng; refresh/đăng nhập lại | Lưu đúng thông tin hợp lệ, báo lỗi và giữ dữ liệu nhập cần sửa |

### B. Cửa hàng, giỏ và dự toán

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| STORE-01 | P1 | Trang chủ → danh mục → nhóm → chi tiết; tìm tiếng Việt có/không dấu; không có kết quả | Điều hướng, lọc/tìm và trạng thái rỗng hoạt động; không hiện hàng ngừng bán ngoài quy định |
| STORE-02 | P1 | Chạy sắp xếp/phân trang/bộ lọc đang có; mở sản phẩm không tồn tại | Kết quả nhất quán, giữ bộ lọc; trang lỗi thân thiện |
| STORE-03 | P0 | Thêm P1/P2, thêm lại cùng hàng; cập nhật lượng; xóa; tải lại trang/đăng nhập | Giỏ/tổng tiền đúng và không nhân đôi ngoài thao tác yêu cầu |
| STORE-04 | P0 | Lượng 0/âm/quá lớn; số lẻ cho bao và m³; ô trống | Chấp nhận độ chính xác đúng đơn vị, chặn dữ liệu sai |
| STORE-05 | P1 | Giỏ rỗng; chọn sản phẩm P3/P4/P5; chọn kho chưa đủ hàng | Nút/giải thích đúng; không tạo đơn không hợp lệ |
| STORE-06 | P1 | Dự toán từng loại nhà, diện tích/số tầng khác nhau; giá trị biên và sai | Lượng và tổng khớp định mức/làm tròn; có cảnh báo chỉ là ước lượng |
| STORE-07 | P1 | Đổi sáng/tối, menu mobile, nút liên hệ/footer và chat nổi | Thao tác không bị che, không mất trạng thái trang |

### C. Báo giá và đơn bán

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| SALE-01 | P0 | A gửi giỏ 10 P1 yêu cầu báo giá; kiểm tra bên S1 | Đúng khách, lượng, địa chỉ, tổng và trạng thái requested |
| SALE-02 | P1 | A sửa/rút yêu cầu chưa xử lý; thử sửa/rút sau khi đã gửi báo giá | Cho phép đúng trạng thái; lịch sử được giữ; trạng thái sai bị chặn |
| SALE-03 | P0 | S1 gửi giá chuẩn, hạn còn hiệu lực; thử giá âm/ngày quá hạn | Báo giá hợp lệ tới khách; dữ liệu sai không được lưu |
| SALE-04 | P0 | S1 gửi giá dưới danh mục; M1 duyệt hoặc từ chối có lý do | Chờ duyệt trước khi khách chấp nhận; từ chối trả về bước sửa |
| SALE-05 | P0 | AD1 lập báo giá giảm giá rồi tự duyệt; AD2 duyệt lại | AD1 bị chặn, AD2 được duyệt; lịch sử ghi đúng người |
| SALE-06 | P1 | Báo giá thiếu tồn; ghi ngày dự kiến/vật liệu thay thế; S1 từ chối yêu cầu | Khách thấy ghi chú hoặc lý do; báo giá không bị hiểu là đã giữ hàng |
| SALE-07 | P0 | A chấp nhận báo giá hợp lệ; thử lại bằng double click/refresh/form cũ; báo giá hết hạn | Một đơn và một nghĩa vụ; hết hạn không được nhận; chưa giảm tồn |
| SALE-08 | P0 | S1 xác nhận/từ chối đơn; thiếu lý do từ chối; xác nhận lại | Chuyển đúng trạng thái, nghĩa vụ khi từ chối đúng; không xử lý lặp |
| SALE-09 | P1 | Tìm/lọc báo giá và đơn theo mã/trạng thái; phân trang | Chỉ trả hồ sơ phù hợp; mở đúng chi tiết |

### D. Hợp đồng và lịch thanh toán

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| CONTRACT-01 | P0 | Từ đơn đã xác nhận tạo hợp đồng với lịch DH-GIAO | Tổng các đợt=1.000.000; tổng bắt buộc trước xuất=cọc=500.000; lưu đúng |
| CONTRACT-02 | P0 | Tổng lịch sai, thứ tự trùng, tiền âm, cọc vượt tổng, mốc cố định thiếu ngày, sau giao thiếu ngày hoặc bắt buộc trước xuất | Chặn từng biến thể; không lưu lịch dở dang |
| CONTRACT-03 | P1 | Sửa bản nháp; trình M1; yêu cầu sửa có lý do; sửa và trình lại | Phiên bản/người sửa/trạng thái/lý do nhất quán |
| CONTRACT-04 | P0 | AD1 lập/trình rồi tự duyệt; M1/AD2 duyệt | Chặn tự duyệt; lưu sự kiện người duyệt độc lập |
| CONTRACT-05 | P0 | A đồng ý hoặc yêu cầu sửa; bỏ lý do sửa; thử xác nhận hai lần | Chỉ chủ hợp đồng phản hồi đúng trạng thái; ghi nhận đúng một lần |
| CONTRACT-06 | P0 | S1 tải bản ký trước/sau đủ duyệt và khách xác nhận; file PDF/JPG/PNG, sai loại, quá 10 MiB | Chỉ hợp lệ mới chuyển signed; file không hợp lệ không chuyển trạng thái |
| CONTRACT-07 | P1 | Tải PDF hợp đồng ở các trạng thái được cung cấp; xem tiếng Việt/nhiều dòng | Đúng khách, đơn, giá, lịch, điều khoản; không lỗi font/cắt nội dung |
| CONTRACT-08 | P0 | M1 hủy trước ký có lý do; thử hủy đã ký hoặc sửa lịch đã ký/đã phân bổ | Chặn thay đổi lịch sử; hủy hợp lệ điều chỉnh đơn/nợ đúng |

### E. Pickup, tiền và VietQR

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| PAY-01 | P0 | B đặt pickup 2 P1 ở K1; S1 xác nhận, ký hợp đồng có lịch 100% trước xuất | Đúng kho, tổng 200.000; lịch thiếu tiền trước xuất bị chặn |
| PAY-02 | P0 | Pickup thiếu hàng vẫn gửi yêu cầu; S1 xác nhận khi chưa đủ và sau khi có hàng | Có ghi chú thiếu; không xác nhận vượt khả dụng; đủ hàng mới xác nhận |
| PAY-03 | P0 | B sửa/hủy pickup chưa trả; thử sửa/hủy sau khi trả tiền | Đúng trạng thái cho phép; cập nhật nợ/giữ hàng; không làm mất lịch sử đã trả |
| PAY-04 | P0 | A1 ghi DH-GIAO 300.000 rồi 200.000, cash/bank_transfer | Phân bổ đúng các đợt, dư nợ còn 500.000; điều kiện xuất chỉ đủ sau mốc thứ hai |
| PAY-05 | P0 | Thu 0/âm/vượt dư nợ; thu trước ký; double click và gửi lại form cũ | Không ghi sai tiền hoặc ghi cùng khoản hai lần |
| PAY-06 | P0 | A1 lập hóa đơn trước đủ tiền, sau đủ tiền, rồi lập lại | Chặn sớm; một hóa đơn và lệnh xuất; số tiền tại lập đúng |
| PAY-07 | P1 | Hẹn quyết toán ngày hợp lệ/quá khứ, thiếu ghi chú; xem lịch sau ký và sau giao | Mốc hạn và số tiền đúng; chặn dữ liệu không hợp lệ |
| PAY-08 | P0 | AD1 thêm/sửa tài khoản ngân hàng; số liệu sai; sửa khi còn QR pending | Lưu thông tin hợp lệ, lịch sử đúng; không làm sai QR đang chờ |
| PAY-09 | P0 | A1 tạo QR đúng số tiền/dư nợ; tạo trùng, số lẻ VND, vượt dư nợ, trước ký | QR hợp lệ có đúng ngân hàng, tài khoản, tiền/nội dung; biến thể sai bị chặn |
| PAY-10 | P0 | Khách báo đã chuyển → A1 xác nhận bằng mã ngân hàng; thử xác nhận lại/trùng mã | Lần khách báo chưa tự tăng paid; kế toán xác nhận tạo đúng một payment |
| PAY-11 | P0 | Hủy QR có lý do; thử dùng QR hủy và URL của khách khác; AD1 tạo/xác nhận VietQR | Không thu từ QR hủy/khác chủ; admin mặc định không được tạo/xác nhận |

### F. Nhập hàng, kho, giao nhận

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| STOCK-01 | P1 | W1 thêm/sửa NCC và K1/K2; mã trùng/thiếu trường | Lưu đúng, mã trùng bị chặn; lựa chọn xuất hiện trong form liên quan |
| STOCK-02 | P0 | W1 lập MH-01; M1 duyệt; AD1 tự duyệt đề nghị do mình lập | Đúng hàng/giá/kho; chưa duyệt không nhận; tự duyệt bị chặn |
| STOCK-03 | P0 | Nhận MH-01 hai đợt 8 rồi 12; nhận quá số còn lại/0 toàn bộ/nhận lặp | Tồn và phải trả theo các mốc mục 2; không nhận vượt hoặc ghi trùng |
| STOCK-04 | P1 | Ghi thiếu/hỏng từ NCC; số vượt phần chưa nhập; giải quyết sự vụ thiếu ghi chú | Đúng hạn mức và lịch sử xử lý; hồ sơ sai bị chặn |
| STOCK-05 | P0 | Kiểm kê P1 100→97; duyệt/từ chối; tự duyệt; đổi tồn giữa lúc đếm và duyệt | Chỉ duyệt hợp lệ mới đổi tồn; tồn đã thay đổi yêu cầu đếm lại |
| STOCK-06 | P0 | Xuất DH-GIAO trước lệnh kế toán; sau lệnh; kho thiếu; xuất lặp | Chỉ xuất một lần khi đủ điều kiện; tạo phiếu/biến động và giảm đúng kho |
| STOCK-07 | P0 | Đơn pickup ở K1; thử xuất ở K2; đơn khác tranh lượng đã giữ | Bảo vệ đúng kho và lượng dành cho đơn; không xuất âm |
| STOCK-08 | P1 | Tìm tồn, xem biến động và cảnh báo tồn tối thiểu | Khớp các phiếu; không cộng nhầm tồn hai kho |
| DEL-01 | P0 | Lập chuyến trước/sau xuất; nhận hàng rồi bàn giao đủ | Chỉ đúng thứ tự mới được làm; đơn/chuyến cùng delivered; lịch sau giao được kích hoạt |
| DEL-02 | P0 | Bàn giao có sự cố; ghi người nhận/tình trạng; hẹn lại, nhận và giao lại | Giữ biên bản cũ, thêm lần giao mới; cập nhật trạng thái đúng |
| DEL-03 | P1 | Gộp ≥2 chuyến; đổi thứ tự điểm dừng; tải trọng/thể tích vượt giới hạn; tọa độ sai | Nhóm hợp lệ giữ đúng đơn; chặn quá tải/sai dữ liệu; tuyến hiển thị rõ |
| DEL-04 | P0 | Pickup trả 100.000 rồi thử xuất; trả đủ, xuất và ghi người nhận | Chỉ đủ 200.000 mới xuất; bàn giao đúng người, không tạo chuyến công trình |
| DEL-05 | P0 | Hai profile cùng xác nhận đơn cuối/tồn cuối, xuất hoặc bàn giao cùng hồ sơ | Một nghiệp vụ thành công; bên còn lại nhận thông báo và không làm lệch tồn/trạng thái |

### G. Đổi trả, ghi có và nhà cung cấp

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| RETURN-01 | P0 | Khách yêu cầu trả/đổi sau giao; trước giao; vượt lượng; nhiều yêu cầu cộng dồn vượt lượng | Chỉ đơn/khách/lượng hợp lệ được tạo |
| RETURN-02 | P0 | D1 thu hồi đúng/sai số lượng; W1 kiểm trước/sau thu hồi; đánh dấu tái sử dụng/hỏng | Tuân thủ thứ tự; hàng tái sử dụng tăng tồn, hàng hỏng không tăng tồn bán được |
| RETURN-03 | P0 | A1 quyết toán trả khi đơn chưa trả hết rồi quyết toán lại | Kết quả 800.000/500.000/còn 300.000 theo mục 2; không điều chỉnh lặp |
| RETURN-04 | P0 | Đơn đã trả đủ, trả 2 P1; hoàn 50.000, cấn trừ 150.000; gửi lặp | Khoản ghi có 200.000 dùng hết đúng một lần; tiền/nợ lịch được điều chỉnh đúng |
| RETURN-05 | P0 | Hoàn/cấn vượt số dư, âm, cấn vào nợ của khách khác hoặc đơn chưa đủ điều kiện | Bị chặn và số dư không đổi |
| RETURN-06 | P0 | Đổi đủ tồn; đổi thiếu tồn → đề xuất mua bù → duyệt/nhận → giao hàng thay thế | Không sinh khoản thu sai; liên kết đúng phiếu đổi/mua/chuyến; hoàn tất sau bàn giao |
| RETURN-07 | P0 | Trả hàng hỏng cho NCC đúng/sai nguồn; thiếu xác nhận trách nhiệm hoặc bằng chứng | Chỉ hồ sơ đủ chứng cứ và NCC từng giao sản phẩm được chấp nhận |
| RETURN-08 | P0 | A1 quyết toán trả NCC đúng/sai khoản phải trả, vượt giá trị, gửi lại | Giảm đúng khoản phải trả, không trừ hai lần; lịch sử đầy đủ |
| SUP-01 | P1 | Ghi xác nhận công nợ NCC, chọn khoản nợ/payment, người liên hệ, ngày, đính kèm | Lưu và tải đúng chứng từ; nội dung được xem đúng hồ sơ |
| SUP-02 | P0 | Gắn xác nhận NCC vào khoản nợ/payment sai đối tượng; file sai/quá cỡ; người không có quyền tải | Chặn từng biến thể; không lộ chứng từ |

### H. Đối chiếu công nợ khách hàng

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| DEBT-01 | P0 | A1 lập snapshot theo khách/kỳ có bán, thu, trả, cấn trừ | Đầu kỳ + tăng - giảm = cuối kỳ; mỗi khoản chỉ tính một lần; khớp chứng từ mục 2 |
| DEBT-02 | P1 | Kỳ rỗng, ngày chốt tương lai, bắt đầu sau kết thúc; kỳ qua ranh ngày | Trình bày rỗng rõ; chặn ngày sai; giao dịch đúng kỳ |
| DEBT-03 | P0 | Gửi snapshot → A xác nhận; lập bản mới sau phát sinh | Đúng người/phiên bản; bản cũ giữ nguyên nội dung |
| DEBT-04 | P0 | A báo chênh lệch thiếu/có lý do → A1 giải quyết → A xác nhận; phản hồi lặp | Đúng vòng đời và lịch sử tin nhắn; không sửa thẳng số dư vì phản hồi |
| DEBT-05 | P0 | B mở đối chiếu của A; sai vai trò mở trang nội bộ | Không lộ hồ sơ; quyền được chặn cả URL |

### I. Danh mục, tài khoản và lịch sử duyệt

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| ADMIN-01 | P1 | S1/W1 đề xuất thêm/sửa sản phẩm và nhóm; M1 duyệt/từ chối | Chỉ duyệt mới áp dụng; lịch sử giá đúng; chứng từ đã lập giữ giá giao dịch |
| ADMIN-02 | P0 | AD1 tự duyệt danh mục mình lập; duyệt lặp; dữ liệu mã/giá/đơn vị sai | Chặn tự duyệt/trạng thái sai và nhập liệu không hợp lệ |
| ADMIN-03 | P1 | M1 sửa hạn mức khách, giá trị sai; mở đơn liên quan | Lưu hạn mức đúng; không suy diễn có chặn bán tự động nếu UI/code không quy định |
| ADMIN-04 | P0 | AD1 tạo mỗi vai trò; mã/email/phone trùng; password ngắn; khóa/mở khóa/reset password | Đúng vai trò/trạng thái; mật khẩu mới dùng được, thông tin sai bị chặn |
| ADMIN-05 | P0 | Tự hạ/khóa admin; hạ admin cuối; đổi khách có lịch sử thành nhân viên và ngược lại | Bảo vệ admin và lịch sử; đổi vai trò hợp lệ chỉ áp dụng cho tài khoản đủ điều kiện |
| ADMIN-06 | P1 | Xem ma trận và lịch sử duyệt; lọc theo loại/mã nếu có | Khớp permission đã seed, đúng người/lý do/trạng thái/phiên bản; không lộ ngoài quyền |

### J. Báo cáo và file xuất

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| REPORT-01 | P0 | Tạo đủ 4 loại finance/inventory/sales/logistics theo vai trò | Đúng loại được cấp, kỳ và số liệu của bộ dữ liệu QA |
| REPORT-02 | P1 | Từng bộ lọc nhóm/khách/nhân viên/NCC và tổ hợp; bộ lọc tồn kho không hỗ trợ | Lọc đúng phạm vi; tổ hợp không hỗ trợ bị từ chối, không âm thầm bỏ lọc |
| REPORT-03 | P0 | M1 duyệt/từ chối có lý do; AD1 tự duyệt báo cáo mình tạo; xuất trước duyệt | Đúng vòng đời; chặn tự duyệt; chỉ báo cáo approved được xuất/in |
| REPORT-04 | P0 | Tải XLSX/PDF và trang in; kỳ rỗng/nhiều dòng/tiếng Việt/chuỗi bắt đầu = | Đúng định dạng và tổng, font/khổ A4; chuỗi được giữ là văn bản; không cắt số liệu |
| REPORT-05 | P0 | Sau duyệt phát sinh thêm thu/nhập/trả rồi mở lại bản cũ và lập bản mới | Snapshot cũ giữ nguyên; bản mới phản ánh thay đổi; kiểm riêng tồn/nợ tại ngày chốt |

### K. Trợ lý AI và kho tri thức

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| AI-01 | P1 | AD1 lưu cấu hình Groq hợp lệ/sai/để trống theo form; người khác mở URL cấu hình | Thông báo rõ; khóa không lộ trong giao diện/ảnh; chặn trái quyền |
| AI-02 | P1 | Sửa tri thức khách hàng; câu hỏi trước/sau sửa | Nội dung hợp lệ được dùng; định dạng sai không làm hỏng kho cũ |
| AI-03 | P1 | Upload Markdown QA → xem/chỉnh chunks → lưu nháp → duyệt index | Đúng bước draft/queued/indexing/ready; nháp chưa được trích dẫn công khai |
| AI-04 | P1 | File rỗng/sai loại/>1 MiB; chunk rỗng/quá dài; sửa trong lúc index | Báo lỗi dễ hiểu, không mất bản đang dùng |
| AI-05 | P1 | Tìm thử tài liệu; hỏi mã QA-CMC-ALPHA trên trang chat và chat nổi; mở nguồn | Trả đúng nội dung có căn cứ; nguồn mở đúng tài liệu/chunk được công bố |
| AI-06 | P0 | Tắt/xóa tài liệu đã index rồi hỏi/mở lại URL nguồn; thử mở tài liệu nháp | Không tiếp tục lộ nguồn không công bố; xóa/tắt phản ánh sau xử lý hệ thống |
| AI-07 | P1 | Câu ngoài phạm vi, yêu cầu bí mật, nội dung kiểu chỉ dẫn trong tài liệu; hỏi giá/tồn không có bằng chứng | Không lộ bí mật/quyền nội bộ; không khẳng định thông tin không được hỗ trợ |
| AI-08 | P1 | Chat nhiều lần, gửi rỗng/quá dài; dịch vụ Groq/embedding/vector/worker không sẵn sàng | Giới hạn theo UI/config; thông báo chờ/lỗi/phương án fallback, không quay vô hạn. Mất dịch vụ cần fixture vận hành riêng |

### L. Sao lưu, khôi phục và trải nghiệm chung

| ID | Ưu tiên | Thao tác qua UI | Kết quả mong đợi |
| --- | --- | --- | --- |
| BACKUP-01 | P0 | AD1 tạo backup, xem chi tiết, tải .cmcbackup và upload lại | Đúng metadata; upload/inspect không tự thay đổi dữ liệu hiện tại |
| BACKUP-02 | P0 | Upload sai loại, hỏng, sai khóa hoặc vượt 128 MiB; user khác mở URL backup | Từ chối rõ, dữ liệu hiện tại không đổi; không lộ tệp |
| BACKUP-03 | P0 | Trên QA riêng: tạo backup B0; thêm hồ sơ marker; restore sai mật khẩu/sai KHOI PHUC | Restore bị chặn; marker và số dư còn nguyên |
| BACKUP-04 | P0 | Restore B0 đúng; đăng nhập lại tài khoản trong B0; kiểm tra marker, tiền/tồn/file | Marker sau B0 biến mất; số dư/file trước B0 trở lại; có backup an toàn trước restore; phiên cũ hết hiệu lực |
| BACKUP-05 | P0 | Hai profile tạo/restore đồng thời; giữ form ghi dữ liệu khi restore | Không có hai restore chồng nhau hay trạng thái ghi dở; kiểm lại UI và số dư. Ca này cần điều phối thời điểm |
| BACKUP-06 | P1 | Sau restore mở tri thức, chờ hàng đợi index rồi chat lại | Trạng thái xây lại index rõ; nội dung khớp bản đã khôi phục |
| UX-01 | P1 | Mỗi màn hình: 1440×900, 768×1024, 390×844, 320×800; sáng/tối | Không tràn toàn trang/che nút; bảng cuộn cục bộ hoặc đổi bố cục đọc được |
| UX-02 | P1 | Chỉ dùng bàn phím; Tab/Shift-Tab/Enter/Escape, zoom 200%, lỗi biểu mẫu | Có focus nhìn thấy, nhãn/lỗi rõ, modal/menu đóng mở được, không kẹt bàn phím |
| UX-03 | P1 | Reload/Back/Forward, form session hết hạn, mất mạng rồi gửi lại, double click | Thông báo rõ, không có giao dịch trùng; dữ liệu nhất quán sau tải lại |
| UX-04 | P2 | Danh sách rỗng/nhiều trang, chuỗi dài, tiếng Việt, link hỏng, file tải xuống | Trình bày đọc được, bộ lọc được giữ, file mở được và thông báo lỗi phù hợp |

## 5. Thứ tự chạy và các chuỗi bắt buộc

1. **Chuẩn bị và smoke:** ghi baseline; đăng nhập 7 vai trò; mở mọi màn hình phù hợp, kể cả module mới AI, QR, đối chiếu và backup. Màn hình không vào được là blocker cho ca phụ thuộc.
2. **Dữ liệu nền:** ADMIN-01, STOCK-01 → duyệt sản phẩm/nhóm → nhập tồn qua STOCK-02/03. Không sửa thẳng tồn hoặc đánh dấu duyệt bằng database.
3. **E2E-01 giao công trình:** khách A → SALE-01/03/07/08 → CONTRACT-01/03/05/06 → PAY-04/06 → STOCK-06 → DEL-01. Đối chiếu tổng 1.000.000, đã trả 500.000, tồn giảm 10, hạn còn lại sau giao.
4. **E2E-02 pickup:** PAY-01/03 → hợp đồng đúng điều kiện → DEL-04. Ghi riêng tồn đầu kỳ. Có nhánh thiếu tồn, hủy chưa trả và đơn tranh hàng.
5. **E2E-03 thanh toán QR:** dùng đơn riêng đã ký → PAY-08/09/10. Khách chỉ báo đã chuyển, kế toán mới xác nhận tiền; thử lại form cũ và mã ngân hàng trùng.
6. **E2E-04 trả/đổi:** dùng bản sao nghiệp vụ của E2E-01 → RETURN-01 đến 08. Tách đơn chưa trả hết, đơn trả đủ và đổi thiếu tồn để có số kiểm chứng độc lập.
7. **E2E-05 sổ nợ/báo cáo:** SUP-01/02, DEBT-01 đến 05, REPORT-01 đến 05 sau khi đã có phát sinh. Kết quả phải khớp các chứng từ UI đã thu thập.
8. **Quyền và kiểm tra lỗi:** lặp quyền/sở hữu/gửi lại đối với từng nhóm; thử độc lập người lập/người duyệt; chạy hai phiên cho tồn cuối và thanh toán trùng.
9. **AI và UI đa thiết bị:** AI-01 đến 08; UX-01 đến 04 cho toàn bộ màn hình trong inventory dưới đây.
10. **Backup/restore cuối cùng:** BACKUP-01 đến 06 trên môi trường QA dùng một lần. Sau restore chạy smoke 7 vai trò, xem đơn, tồn, nợ, file và AI lại.

Không dùng admin để thay tất cả vai trò trong E2E. Mỗi bước phải được thực hiện bằng đúng người phụ trách để phát hiện vấn đề giao việc và quyền.

## 6. Inventory để kiểm soát bao phủ

| Controller / nhóm màn hình | Ca kiểm thử chính |
| --- | --- |
| AuthController; auth/login, register, forgot/reset-password | AUTH-01 đến 08 |
| StoreController; home/catalog/product/cart/profile/quotes/contracts/orders/invoice | AUTH-08/09, STORE, SALE, CONTRACT, PAY, RETURN |
| EstimateController; store/estimate | STORE-06 |
| StaffController; 6 dashboard nhân viên, các section báo giá/đơn/hợp đồng/tài chính/kho/giao/đổi trả | SALE, CONTRACT, PAY, STOCK, DEL, RETURN; AUTH-07, UX |
| ManagementController; sản phẩm/nhóm/NCC/kho/tài khoản/hạn mức/ma trận | STOCK-01, ADMIN; AUTH-07 |
| VietQrController; bank-accounts, staff/vietqr, my/transfers | PAY-08 đến 11; AUTH-08 |
| DebtReconciliationController; staff và my/debt-reconciliations | DEBT |
| SupplierDebtConfirmationController; danh sách, tạo và tải chứng từ | SUP |
| ReportController; reports, report-print, XLSX/PDF | REPORT |
| AssistantController, AiSetupController; assistant/chat nổi/AI setup | AI-01, AI-05/07/08 |
| KnowledgeBaseController, KnowledgeDocumentController; knowledge/documents/document/source | AI-02 đến 06 |
| BackupController; management/backups, backup detail/upload/download/restore | BACKUP |
| Approval history và các phần lịch thanh toán dùng chung | ADMIN-06, CONTRACT, PAY, DEBT |
| Layout store/staff, navigation, theme, notices, pagination | STORE-07, UX |

Trước khi thực thi, lấy inventory route/view của đúng bản code đang chạy và nối từng action mới vào một ca. Nút không xuất hiện hoặc endpoint có trong source nhưng không tới được từ UI: ghi thiếu đường truy cập UI, không dùng API thay thế để tính đã bao phủ. Welcome/default asset chỉ cần test nếu thực sự được route sử dụng.

## 7. Ghi nhận kết quả và xử lý lỗi

Mỗi biến thể dùng nhật ký với các cột: Run ID | Case ID/biến thể | phiên bản | browser/viewport | tài khoản/vai trò | mã hồ sơ | tiền/tồn trước | bước thực tế | kỳ vọng | kết quả thực tế | tiền/tồn sau | Pass/Fail/Blocked/Not run | link ảnh/file | mã lỗi.

- Ghi bằng chứng ở mốc trước/sau mỗi thao tác P0; ảnh phải thấy mã chứng từ/trạng thái/tổng. Lưu PDF/XLSX đã tải và ảnh đối chiếu số tiền. Che khóa API, mật khẩu và dữ liệu ngoài QA.
- Fail: ứng dụng phản hồi khác kỳ vọng đã thống nhất, kể cả có toast thành công nhưng tiền/tồn sai. Blocked: không có môi trường, email, worker, tài khoản hay dữ liệu điều kiện. Không gộp Blocked vào Pass.
- Báo lỗi có bước tái hiện tối thiểu, vai trò, hồ sơ, kỳ vọng/thực tế và tác động. P0 sai quyền/sai tiền/sai tồn/chặn E2E; P1 chức năng chính; P2 lỗi trình bày không chặn.
- Sau sửa: chạy lại ca lỗi và ca liền trước/liền sau; với thay đổi chung về quyền/tiền/tồn chạy lại chuỗi E2E liên quan. Không reset database giữa chuỗi đang cần chứng minh lịch sử.
- Kết thúc mỗi đợt lưu snapshot số dư trên UI và danh sách ca còn thiếu. Ước lượng ban đầu: chuẩn bị 0,5 ngày; chức năng/E2E 1,5–2 ngày; quyền/đa thiết bị/AI/restore và biên bản 1 ngày; chưa gồm sửa lỗi và retest.

## 8. Tiêu chí kết thúc

- Mọi dòng ca và biến thể có trạng thái/bằng chứng; inventory action UI có ca tương ứng.
- Tất cả P0 và các chuỗi E2E bắt buộc Pass; không còn lỗi nghiêm trọng về tiền, tồn, quyền, chứng từ hay restore. Chức năng bị Blocked phải liệt kê rõ và chưa được nghiệm thu.
- Các vai trò làm được việc được cấp, không làm được việc bị cấm; quyền sở hữu khách A/B và admin/VietQR được kiểm riêng.
- Tổng tiền, phân bổ, ghi có, phải thu/phải trả, tồn từng kho và snapshot báo cáo khớp các số kiểm chứng bằng giao diện.
- PDF/XLSX/hợp đồng/backup/chứng từ mở được; màn hình chính dùng được trên mobile và desktop.
- UI testing chứng minh hành vi quan sát được. Nó không tự chứng minh rollback ở mọi điểm lỗi, toàn vẹn MongoDB nội bộ, chịu tải hoặc độ bền transaction; các phần đó cần đợt kiểm thử kỹ thuật riêng nếu đưa vào tiêu chí phát hành.

## 9. Nguồn đối chiếu trong checkout

- laravel/routes/web.php — inventory route, middleware và action.
- laravel/app/Support/PermissionMatrix.php — quyền mặc định, gồm ngoại lệ VietQR của admin.
- laravel/app/Services/CommerceWorkflow.php, FulfillmentWorkflow.php, PaymentScheduleWorkflow.php, ApprovalWorkflow.php — chuyển trạng thái và điều kiện tiền/kho/duyệt.
- laravel/app/Services/DebtReconciliationWorkflow.php, SupplierDebtConfirmationWorkflow.php, VietQrWorkflow.php — các module mới.
- laravel/app/Http/Controllers/ReportController.php — snapshot, quyền xuất và định dạng XLSX/PDF.
- laravel/app/Http/Controllers/BackupController.php, KnowledgeDocumentController.php — bước restore, upload và các giới hạn file nêu trong kế hoạch.
- laravel/resources/views/ — inventory màn hình và biểu mẫu thực tế. Các claim nghiệm thu trong tài liệu archive không được tính là kết quả của lần kiểm thử này.

## 10. Nhật ký thực hiện ngày 03/11/2026

Đã thao tác trong trình duyệt nằm trong ChatGPT tại môi trường http://localhost:8000; không điều khiển Edge hoặc ứng dụng Windows khác.

| Phạm vi | Kết quả quan sát | Trạng thái |
| --- | --- | --- |
| Khách chưa đăng nhập — Dự toán nhanh, màn hình 390×844 | Chọn nhà phố bê tông cốt thép, 100 m² mỗi tầng, 2 tầng. UI hiển thị 300 bao xi măng, 18 m³ cát, 5 m³ đá, 5.000 kg thép, 15.000 viên gạch; nguồn minh họa hiện dưới từng dòng; giá và nút thêm giỏ được ẩn | Đã kiểm tra một ca |
| Dự toán nhanh — gợi ý AI chưa cấu hình | Gửi mô tả mẫu không chứa dữ liệu riêng tư; UI báo AI chưa sẵn sàng và giữ đường nhập thủ công. Đường thành công/kiểm tra allowlist được kiểm bằng HTTP fake trong Feature test, không gọi dịch vụ thật | Đã kiểm tra fallback qua UI |
| Danh mục khách | Trang sản phẩm hiển thị danh mục, đơn vị, giá, số trang; bấm “Cát & đá” giữ bộ lọc và hiện 4 sản phẩm. Dữ liệu đang có đánh dấu các sản phẩm là hết hàng, nên không thử đặt hàng | Đã kiểm tra trang/lọc; nhánh còn hàng chưa kiểm tra |
| Quản trị định mức Dự toán nhanh | Feature test kiểm quyền admin, nhập bản cũ không ghi đè, tạo phiên bản kích hoạt ngay, giữ lịch sử, quay lại bản cũ và kiểm tra khối lượng đổi theo phiên bản | Đã kiểm tra phía ứng dụng; cần tài khoản admin QA để nghiệm thu UI trình duyệt |
| Giỏ hàng khách | Giỏ trống có trạng thái rõ và lối quay lại sản phẩm | Đã kiểm tra một ca |
| Trợ lý, khách chưa đăng nhập | Gửi lời chào và nhận phản hồi từ nhánh trả lời cục bộ; ca này không cần gọi Groq | Đã kiểm tra một ca |
| Các bộ kiểm thử hồi quy của phần đã sửa | Đã chạy chọn lọc kiểm thử Feature cho menu, dashboard, bán hàng, kho, kế toán, quản lý/báo cáo, trợ lý và dự toán. Đây là kiểm tra phía ứng dụng, không tính thay cho ca UI ở các mục A–L | Đã chạy chọn lọc |
| Hồ sơ khách và dashboard nhân viên trong trình duyệt | Chưa đăng nhập được bằng tài khoản QA khách/nhân viên độc lập; không có thông tin tài khoản QA được cung cấp | Chưa kiểm tra UI; cần tài khoản QA |
| Chuỗi đặt hàng, tiền, tồn kho, duyệt, bàn giao, báo cáo, backup và các vai trò | Chưa thực hiện đầy đủ các ca trong các mục A–L; không coi kiểm thử Feature hoặc nội dung tài liệu nguồn là bằng chứng UI | Chưa chạy / cần môi trường và tài khoản QA |
| Hồi quy Feature toàn dự án | Lượt đầu: 228 pass, 2 fail, 4 skip. Hai fail do fixture chọn phát sinh sau ngày chốt; đã sửa mốc fixture và truyền kỳ bắt đầu rõ ràng. Chạy lại DebtReconciliationTest: 11 pass / 60 assertions. Không chạy lại toàn bộ suite sau thay đổi fixture test-only | Fixture test đã sửa; workflow công nợ không đổi |

Ca dự toán đã quan sát dùng dữ liệu định mức đánh dấu rõ là minh họa. Cách nhập đã thống nhất là diện tích một tầng điển hình; không thay đổi engine tính. Cần chuyên gia xác nhận định mức trước khi xem kết quả là khối lượng kinh doanh. Mục 13 và tiêu chí kết thúc tại mục 8 chưa hoàn thành.
