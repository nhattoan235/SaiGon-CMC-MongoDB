# Rà soát thiết kế MongoDB trong báo cáo Phase 5

Ngày: 18/10/2026.
Trạng thái: REVIEW VÀ ĐỀ XUẤT ĐỂ CHỐT, CHƯA ĐƯỢC COI LÀ THIẾT KẾ ĐÃ DUYỆT.
Nguồn báo cáo: [PDF Phase 5](D:/Group1_Quan_ly_cua_hang_vat_lieu_xay_dung_phase5.pdf), 152 trang.
Nguồn code đối chiếu: nhánh Toan, commit f9860db0e594aefb8b2bb86ae81bb2306086ac17.

## 1. Kết luận

Thiết kế ở trang 76-77 là nền tảng khái niệm hợp lý: nhúng dữ liệu chi tiết có cùng vòng đời với chứng từ cha, tham chiếu tới các thực thể độc lập. Có thể dùng hướng này cho bản MongoDB.

Tuy nhiên, sơ đồ 15 collection chính ở trang 77 CHƯA ĐỦ để dùng nguyên trạng làm schema vật lý thay thế project hiện có. Cần sửa hai mũi tên tham chiếu không nhất quán, bổ sung liên kết sở hữu chứng từ và mô tả các aggregate còn thiếu cho kho, thanh toán, nhập nhiều đợt, giao nhận và đổi trả. Kiểu số ở sơ đồ lớp trang 90 cũng cần được chốt lại.

Không có yêu cầu MongoDB phải giữ 39 collection giống 39 bảng SQL; cũng không nên ép toàn bộ dữ liệu vào đúng 15 collection chính. Tiêu chí là giữ đủ nghiệp vụ và lịch sử với ranh giới document hợp lý.

Đề xuất: BÁM THIẾT KẾ BÁO CÁO THEO HƯỚNG HYBRID, NHÚNG CÓ CHỌN LỌC, bổ sung schema vật lý và bảng mapping trước khi lập trình. Kế hoạch một collection/một bảng trước đây là phương án tạm khi chưa có PDF; không phải quyết định đã chốt.

## 2. Phạm vi và cách đọc nhận xét

Đã trích xuất văn bản toàn bộ 152 trang; đọc các phần mục tiêu, nghiệp vụ và đặc tả liên quan, phần 3.2, đối chiếu trực quan sơ đồ MongoDB trang 77, sơ đồ lớp trang 90 và một số sơ đồ kho/giao nhận/tài chính trang 129, 141, 144, 146. Đối chiếu với schema, ba workflow, controller, giao diện, cấu hình và test đã khảo sát trong repo.

Phân biệt ba loại nhận xét:

- **Không nhất quán đã xác nhận:** hai vị trí trong tài liệu mô tả khác nhau.
- **Chưa thể hiện:** sơ đồ tóm tắt chưa ghi dữ liệu/ràng buộc cần thiết; không suy ra rằng tác giả không biết hoặc ứng dụng chắc chắn không có.
- **Quyết định cần chốt:** có nhiều phương án hợp lệ; phải thống nhất trước khi triển khai.

P1 = cần giải quyết trước khi coi schema đủ cho việc chuyển đổi giữ nguyên chức năng. P2 = cần làm rõ trong thiết kế chi tiết hoặc đồng bộ báo cáo. Không dùng mức ưu tiên này để khẳng định có lỗi đang xảy ra trên dữ liệu thật.

Số trang trong tài liệu này là số trang PDF, trùng số in trên các trang đã đối chiếu. Các code path dưới đây tương đối với thư mục laravel/ của repo nguồn.

## 3. Những điểm đang hợp lý nên giữ

1. ChiTietDonHang[], ChiTietBaoGia[] và ChiTietNhapKho[] là ứng viên tốt để nhúng vào chứng từ cha vì thường được đọc cùng nhau và xử lý theo vòng đời chứng từ.
2. Thực thể độc lập như hàng hóa, nhà cung cấp, tài khoản, khách hàng nên có tham chiếu; sử dụng reference không làm thiết kế mất tính MongoDB.
3. Có cả _id và mã nghiệp vụ MaDon/MaHang/MaHopDong là hợp lý nếu chốt rõ vai trò, kiểu và unique index của từng loại.
4. Một BienBanNhapHang{} nằm trong MỘT phiếu nhận hàng thực tế có thể hợp lý; vấn đề là không gộp toàn bộ nhiều đợt nhận của một đơn mua vào một biên bản duy nhất.
5. Sơ đồ lớp vẫn có lớp chi tiết không phải lỗi chỉ vì dữ liệu vật lý được nhúng; mô hình đối tượng và mô hình lưu trữ không bắt buộc giống hệt nhau.

Cách chọn embed/reference được hỗ trợ bởi [hướng dẫn mô hình dữ liệu MongoDB](https://www.mongodb.com/docs/manual/data-modeling/best-practices/). Áp dụng theo vòng đời và mẫu truy vấn của app, không theo quy tắc “NoSQL thì phải nhúng hết”.

## 4. Các vấn đề cần xử lý

### F01 - P1 - Hai tham chiếu của HopDong không khớp giữa trang 77 và 90

**Bằng chứng:** trên hình trang 77, đường MaQL từ HopDong kết thúc tại KhachHang; đường MaNV kết thúc tại TaiKhoan. Trang 90 lại ghi MaQL: BanQuanly và MaNV: Nhanvien.

**Ảnh hưởng:** người triển khai có thể lấy nhầm namespace ID của người duyệt/người lập, hoặc nhầm khách hàng với quản lý.

**Đề xuất:** sửa sơ đồ cho đúng đối tượng đã chọn. Nếu dùng tài khoản thống nhất như code hiện tại, các trường created_by/approved_by tham chiếu TaiKhoan có role tương ứng và phải đổi nhãn/mô tả cho rõ. Nếu giữ profile BanQuanLy/NhanVien độc lập, mũi tên phải trỏ đúng profile và mô tả liên kết profile với tài khoản. Không tự coi cách dùng TaiKhoan là sai nếu đó là ý đồ thiết kế, nhưng phải thống nhất cả hai hình.

**Cần chốt:** định danh người thao tác là ID tài khoản hay ID profile.

### F02 - P1 - Chưa thể hiện chuỗi khách hàng, báo giá, đơn hàng, hợp đồng

**Bằng chứng:** trang 77 không ghi tham chiếu khách hàng của DonHang/BangBaoGia, không ghi rõ order/quote references trong HopDong. Trang 59 yêu cầu tạo hợp đồng từ đơn đã xác nhận. Code: StoreController.php:248, :259, :295, :319; CommerceWorkflow.php:308 và :433; migration 2026_10_12_131155_link_contracts_to_orders.php.

**Ảnh hưởng:** không đủ thông tin để lấy “đơn của tôi”, kiểm tra quyền sở hữu, theo dấu báo giá thành đơn/hợp đồng và hỗ trợ hợp đồng cũ đi từ báo giá.

**Đề xuất:** bổ sung customer_account_id hoặc customer_id thống nhất; quote_id/order_id/contract_id theo chiều quan hệ cần truy vấn; invoice.order_id. Chỉ định quan hệ nào bắt buộc, nullable, một-một hay một-nhiều. Giữ quy tắc customer_id của code hiện tại đang trỏ users, không phải customers profile.

**Cần chốt:** bảng reference có kiểu đích rõ ràng; không dùng duy nhất đường nối hình học làm đặc tả.

### F03 - P1 - Một HangHoa.SoLuong không đủ cho quản lý kho hiện có

**Bằng chứng:** trang 77 chỉ có số lượng trên hàng hóa, chưa có Kho/TonKho/BienDongKho/KiemKe. Trang 63 và 68-69 yêu cầu vị trí kho, lịch sử nhập/xuất và kiểm kê. Code: migration create_cmms_tables.php:76-90, :177-205; FulfillmentWorkflow.php:20 và :40; StoreController.php:79.

**Ảnh hưởng:** mất tồn theo từng kho, không phân biệt tồn thực tế và lượng đang giữ, không dựng báo cáo tồn ở quá khứ hoặc kiểm tra book quantity sau kiểm kê.

**Đề xuất:** bổ sung Kho, TonKho với unique(kho_id,hang_hoa_id), BienDongKho append-only và chứng từ kho có các dòng chi tiết. HangHoa.SoLuong chỉ là tổng tính để hiển thị hoặc cache có quy tắc cập nhật, không là số lượng chuẩn thứ hai.

**Kiểm chứng:** tồn kho A=5, B=7 phải được giữ riêng; hai đơn cùng giữ 4 ở kho A không được cùng thành công.

### F04 - P1 - Chưa tách đơn mua được duyệt khỏi từng lần nhận hàng

**Bằng chứng:** trang 77 có PhieuNhapKho và một BienBanNhapHang{}, chưa thể hiện đơn đặt nhà cung cấp, kho và supplier của phiếu. Trang 42, 62-63 mô tả đề xuất/đơn đã duyệt và số lượng thực nhận ít hơn đặt. Code: FulfillmentWorkflow.php:89, :134, :202.

**Ảnh hưởng:** không lưu được phê duyệt trước nhận, hàng nhận thiếu, nhiều đợt nhận và công nợ phát sinh riêng từng đợt.

**Đề xuất:** thêm DeXuatNhap/DonDatNCC có ordered items, received_quantity lũy kế và trạng thái duyệt; PhieuNhapKho đại diện MỘT lần nhận thực tế, tham chiếu đơn mua, nhà cung cấp và kho, nhúng các dòng thực nhận. Giữ BienBanNhapHang{} nếu nó chỉ thuộc lần nhận đó.

**Kiểm chứng:** đặt 10, nhận 4 rồi 6 tạo hai lần nhận và nghĩa vụ phải trả tương ứng; không đặt unique debt.purchase_order_id.

### F05 - P1 - CongNo.TongNo chưa thay thế được sổ thanh toán và credit

**Bằng chứng:** trang 77 chưa ghi phải thu/phải trả, supplier, từng lần thanh toán, số đã trả hoặc credit; trang 29 và 60-61 yêu cầu đối soát, thanh toán từng lần/đặt cọc, cả khách hàng và nhà cung cấp. Code: CommerceWorkflow.php:488, :533, :638; FulfillmentWorkflow.php:698.

**Ảnh hưởng:** chỉ lưu số dư cuối sẽ mất ngày/phương thức/chứng từ thanh toán, khó báo cáo quá khứ và không xử lý đúng hoàn/cấn trừ sau trả hàng.

**Đề xuất:** CongNo có direction, customer/supplier reference, nguồn order/purchase/receipt, amount/paid/due_date/status. Lưu ThanhToan riêng theo từng sự kiện; thêm SoDuKhachHang/Credit và GiaoDichSoDu cho hoàn hoặc cấn trừ. Tổng theo đối tác là truy vấn/view, không thay toàn bộ các khoản nợ nguồn.

**Kiểm chứng:** ghi hai khoản thanh toán khác ngày, trả hàng sau kỳ, rồi lập báo cáo kỳ trước vẫn khớp số liệu trước khi trả hàng.

### F06 - P1 - Chưa có aggregate đủ cho vòng đời đổi/trả hàng

**Bằng chứng:** trang 77 có BienBanSuVu{} dưới LenhXuat nhưng chưa có DoiTra; trang 35-36 và 72-73 yêu cầu thu hồi, kiểm tra, phân loại dùng lại/hỏng, trả NCC, đổi hàng thiếu tồn. Code: FulfillmentWorkflow.php:512, :538, :556, :615, :648, :674, :698.

**Ảnh hưởng:** biên bản sự vụ chỉ ghi sự kiện, chưa đủ trạng thái, lượng đã trả, người thu hồi/kiểm tra/kế toán, đơn nhập bổ sung, shipment thay thế và kết quả công nợ.

**Đề xuất:** thêm DoiTra với order_id/item_id/product_id/customer_id, quantity/type/status; thông tin collection/inspection có thể nhúng; tham chiếu supplier return, restock purchase và replacement shipment. Số dư/cấn trừ có lịch sử độc lập.

**Kiểm chứng:** trả một phần hai lần, đổi khi thiếu hàng, thu hồi hàng hỏng và nhận hàng đổi không cộng/trừ tiền hoặc tồn hai lần.

### F07 - P1 - LenhXuat đang gộp các khái niệm cần phân biệt

**Bằng chứng:** trang 77 chứa xuất kho, giao nhận và biên bản trong LenhXuat; chưa thể hiện shipment và lộ trình. Trang 60-64 phân biệt phát lệnh sau điều kiện thanh toán, xuất hàng, bố trí chuyến và bàn giao. Code: CommerceWorkflow.php:595 và :665; FulfillmentWorkflow.php:241, :291, :350 và :484.

**Ảnh hưởng:** khó phân biệt kế toán cho phép xuất, kho đã trừ tồn, đơn vị vận chuyển đã nhận, hàng đã giao; không thể hiện một xe chở nhiều đơn hoặc các giới hạn tải trọng/thể tích.

**Đề xuất:** chốt rõ LenhXuat là lệnh của kế toán hay phiếu xuất thực tế. Giữ các trạng thái/references riêng cho accounting release, chứng từ xuất, VanChuyen/Shipment và LoTrinh/DeliveryRun. Mô hình vật lý có thể dùng trường nhúng cho release metadata nhưng không nhập nhằng nghiệp vụ.

### F08 - P1 - Biên bản bàn giao đơn lẻ có thể mất lịch sử giao lại

**Bằng chứng:** trang 77 dùng BienBanBanGiao{} và BienBanSuVu{} đơn lẻ. Code đã gỡ unique shipment_id của handovers tại migration 2026_10_09_000010_allow_multiple_handovers_per_shipment.php:11-14; FulfillmentWorkflow.php:484 lưu thêm lịch sử.

**Ảnh hưởng:** nếu cập nhật object cũ khi giao lại, biên bản lần thất bại có thể bị ghi đè.

**Đề xuất:** giữ lịch sử handovers riêng theo shipment; hoặc mảng sự kiện có ID, thời gian, người nhận và kết quả khi đã có chính sách giới hạn/archival. Ưu tiên collection history riêng để truy vấn và không cho mảng tăng vô hạn. Chỉ giữ object “latest_handover” như view/cache nếu cần.

Không suy ra mọi object nhúng đều sai: một biên bản cho một receipt có vòng đời duy nhất vẫn hợp lý.

### F09 - P1 - Kiểu Int/Double ở sơ đồ lớp chưa phù hợp dữ liệu thực tế

**Bằng chứng:** trang 77 chưa nêu kiểu vật lý; trang 90 có SoLuong: Int và DonGia/ThanhTien/TongNo: Double. Code dùng decimal(18,3) cho lượng, decimal(18,2) cho tiền và decimal scale 4 cho định mức/thể tích; StorefrontTest.php:249 kiểm tra số lượng lẻ cho m³/kg.

**Ảnh hưởng:** áp Int sẽ mất số lượng cát/đá/kg lẻ; áp binary Double làm thay đổi độ chính xác tính tiền so với BCMath hiện tại.

**Đề xuất:** đặc tả vật lý dùng Decimal128 tạo từ string cho tiền/lượng với scale theo field, chuẩn hóa về decimal string khi vào PHP. Ngày giờ dùng BSON Date cho thời điểm, YYYY-MM-DD cho ngày thuần; không giữ NgayDat dạng String tùy ý như UML nếu chưa quy ước format/timezone.

Đây là việc cần chốt physical type; không khẳng định UML Double hiện chứng minh chương trình đang lưu sai. [Mô hình dữ liệu tiền tệ chính thức](https://www.mongodb.com/docs/manual/tutorial/model-monetary-data/).

### F10 - P1 - Chi tiết nhúng cần định danh và quy tắc cập nhật

**Bằng chứng:** trang 77 có item arrays nhưng chưa ghi ID của từng dòng. Code hiện dùng quantities[item_id], prices[item_id], received[item_id]; xem resources/views/store/quote.blade.php:26, staff/sales-quotes.blade.php:24, staff/dashboard.blade.php:66.

**Ảnh hưởng:** dùng vị trí mảng làm định danh sẽ sửa sai dòng khi thêm/bớt/sắp xếp; đổi sang ObjectId nguyên trạng sẽ vỡ int route/cart/workflow.

**Đề xuất:** giữ root _id numeric và mỗi child có item_id numeric ổn định, product_id và dữ liệu giao dịch. Item ID không là index của array; giữ ID cũ khi nhập dữ liệu và có counter riêng cho item mới. Read module trả trường id cũ cho form; write dùng parent ID + item_id và predicate hợp lệ. Mọi thao tác item phải kiểm tra parent/owner; không tìm toàn cục bằng item_id rồi bỏ qua quyền.

Nếu định chọn ObjectId cho root, phải mở rộng kế hoạch sửa toàn bộ typed signatures/URLs/forms và có phê duyệt riêng; đó không phải điều bắt buộc của MongoDB.

### F11 - P2 - Dữ liệu lặp cần nguồn chuẩn và chính sách snapshot

**Bằng chứng:** trang 77 có SoLuong ở DonHang và ở từng dòng, giá trong HangHoa và các dòng chứng từ, ChiTietHopDong[] gần trùng ChiTietDonHang[]. Code hiện không có bảng contract_items riêng; CatalogWorkflow.php:27 lưu thay đổi giá theo quy trình.

**Ảnh hưởng:** sửa giá hiện tại có thể vô tình đổi giá đơn/hợp đồng cũ; chỉnh hai bản chi tiết độc lập có thể lệch số lượng/tổng tiền.

**Đề xuất:**

- Giá hiện tại lấy từ HangHoa; giá đã chấp nhận trên báo giá/đơn là snapshot.
- ChiTietHopDong nếu lưu riêng là snapshot tại mốc ký/chốt được chọn, không là bản chi tiết thứ hai tự sửa độc lập.
- Header totals được server tính từ chi tiết và cập nhật cùng document; không tin tổng client gửi lên.
- Không cộng “3 cây + 5 kg” thành một số lượng chung có ý nghĩa nghiệp vụ; bỏ header quantity hoặc mô tả rõ là chỉ số dẫn xuất.
- Thêm snapshot tên/quy cách/đơn vị nếu cần giữ chứng từ lịch sử; chốt rõ vì sao chụp/cập nhật mỗi trường.

### F12 - P2 - Tài khoản, vai trò và bảo mật cần mapping đầy đủ

**Bằng chứng:** trang 77 tách TaiKhoan/KhachHang/NhanVien/BanQuanLy; Quyen dạng một field, chưa ghi trạng thái và khóa đăng nhập. Trang 56, 66-67 yêu cầu vô hiệu hóa, quá 5 lần sai thì khóa và quản trị mở khóa. Repo có 7 role và User thống nhất; AuthController.php:185, User.php, users migration:20-24.

**Đề xuất:** giữ một tài khoản chuẩn cho đăng nhập, role/status/failed_logins/locked_until/remember token/email verification/reset-password fields. KhachHang/NhanVien/BanQuanLy là profile/khái niệm role nếu muốn tách, không tự tạo nhiều tài khoản/mật khẩu cho cùng một người.

Field Matkhau: String trong hình KHÔNG chứng minh mật khẩu đang lưu plain text. Cần ghi rõ hash mật khẩu, ẩn khi serialize và giữ hash cũ khi import. Bổ sung unique/nullable/collation phù hợp cho username/phone/email và quy tắc tham chiếu profile.

Trang 54-55 đặc tả đăng ký theo số điện thoại; code hiện đăng ký bằng email+OTP. Đây là khác biệt yêu cầu cần ghi nhận/chốt đồng bộ báo cáo, không tự đổi chức năng đăng ký trong đợt chuyển database.

### F13 - P1 - Chưa có đặc tả atomicity, ràng buộc và xử lý đồng thời

**Bằng chứng:** phần 3.2 trang 76-77 mới mô tả collection/embed/reference; chưa nêu transaction/index/conditional update. Code có 46 lockForUpdate. Nested paths: inspectReturn tại FulfillmentWorkflow.php:558 gọi createPurchaseOrder ở :596; requestExchangeRestock tại :650 gọi :661.

**Ảnh hưởng:** nhúng dòng đơn chỉ làm cập nhật trong một document đơn dễ hơn; kiểm tra tồn và cập nhật order/debt/stock khác document vẫn có thể race hoặc ghi một phần.

**Đề xuất:** chốt một transaction/session cho mỗi nghiệp vụ đa document, topology hỗ trợ transaction, shared inventory/version write để bảo vệ reservation và các conditional status update. Tách nested transaction thành helper nội bộ. Bổ sung unique codes, nullable partial indexes, tồn theo kho, defaults và chính sách FK/delete trong physical design. Test bằng nhiều connection/process và lỗi giữa chừng.

Tham khảo [transaction integration](https://www.mongodb.com/docs/drivers/php/laravel-mongodb/current/interact-data/transactions/) và [MongoDB write conflict/stale read](https://www.mongodb.com/docs/manual/core/transactions-production-consideration/). Không thể bảo đảm tính đúng bằng cách chỉ bỏ lockForUpdate.

### F14 - P1 - BaoCaoThongKe chưa thể hiện dữ liệu để giữ báo cáo đã duyệt

**Bằng chứng:** trang 77 chỉ có MaBC/LoaiBC/Ngay/MaQL/MaNV; trang 65-66 yêu cầu khoảng kỳ/lọc/Excel/PDF và trang 50 quy trình duyệt. Code: ReportController.php:108, :126, :138, :185, :209, :230, :264.

**Ảnh hưởng:** chỉ tính lại từ số dư hiện tại sẽ làm báo cáo cũ thay đổi, sai tồn/công nợ tại cuối kỳ và không khớp file đã duyệt.

**Đề xuất:** thêm from_date/to_date, filters, snapshot, status/approved_by/review_note và thời điểm lập/duyệt. Giữ snapshot field/row order, kiểu scalar khi export. Giữ event time trên payment/movement/return để dựng báo cáo tại quá khứ.

Snapshot báo cáo có thể lớn dù lưu dạng JSON string; vẫn tính vào giới hạn document. Cần đo kích thước, tránh nhét lịch sử vô hạn vào một document. Nếu vượt ngưỡng, thiết kế phần detail/chunks hoặc lưu file snapshot có manifest và tính nhất quán, phải chốt trước khi thay storage.

### F15 - P2 - Hàng hóa một nhà cung cấp và các dữ liệu hỗ trợ chưa được mô tả

**Bằng chứng:** HangHoa.MaNCC ở trang 77 thể hiện một NCC; code chọn nhà cung cấp theo các đơn đã thực nhận: FulfillmentWorkflow.php:591, :680. Trang 65, 68-70 nói tới lịch sử giá, phê duyệt, tồn tối thiểu; sơ đồ còn chưa ghi các phần này. Code có price_history, catalog_changes và estimation_norms.

**Đề xuất:** MaNCC nếu giữ chỉ là preferred supplier, không giới hạn một hàng chỉ nhập được từ một NCC. NCC thực tế thuộc từng đơn mua/receipt. Giữ lịch sử giá, đề xuất duyệt, active/minimum_stock/weight_kg/volume_m3/direct_purchase_allowed và định mức dự toán ở schema bổ sung.

Không bắt buộc tất cả phải nằm trong hình tóm tắt, nhưng phải có nơi lưu chính thức và mapping khi triển khai.

### F16 - P2 - Cần đồng bộ phần chữ và đặc tả chức năng

**Đã xác nhận:**

- Trang 75 nói thiết kế CSDL quan hệ, trang 76 nói MongoDB collection/document, trang 151 lại gọi mô hình quan hệ hai lần. Sửa thuật ngữ ở các đoạn đang mô tả kết quả hiện tại; đoạn nói mô hình cũ/lịch sử thì có thể giữ.
- Trang 59-60 phân biệt đã duyệt và đã ký nhưng hậu điều kiện viết đã ký dù bước ký ở dòng phụ; bổ sung trạng thái đích theo nhánh, đừng để schema coi approved đồng nghĩa signed.
- Trang 65 có đoạn “cập nhật đơn giá” trong dòng phụ của use case báo cáo; nên chuyển sang danh mục hoặc ghi rõ liên quan, tránh biến báo cáo thành nơi sửa giá.

Đã kiểm tra bố cục đầy đủ trang 129, 141, 144: hình phía trên tiêu đề là phần tiếp tục của mục trước. Không ghi nhận việc hình kiểm kê/phiếu nhập/bàn giao nằm trên các trang này như một lỗi gán nhầm mục.

Các điểm này là chỉnh tài liệu, không là kết luận rằng code hiện có sai tương ứng.

## 5. Phương án MongoDB đề xuất để bạn chốt

### 5.1 Nhúng chi tiết, giữ các sổ/sự kiện độc lập

| Nhóm | Đề xuất |
| --- | --- |
| BangBaoGia | Nhúng ChiTietBaoGia[] có item_id, product_id, snapshot quy cách, lượng/giá/thành tiền; root giữ customer và lifecycle references. |
| DonHang | Nhúng ChiTietDonHang[] có item_id ổn định; root giữ ownership, quote/contract, state, paid, pickup/release metadata. |
| HopDong | Giữ document độc lập; snapshot chi tiết nếu được chốt; reference order/quote/customer và người lập/duyệt/ký. |
| DonDatNCC/DeXuatNhap | Aggregate bổ sung, nhúng ordered items có item_id/received_quantity; duyệt độc lập với nhận hàng. |
| PhieuNhapKho | Mỗi lần nhận một document, nhúng actual receipt items và một biên bản của lần nhận; tham chiếu đơn mua/NCC/kho. |
| Chứng từ kho khác | Nhúng items trong phiếu xuất/kiểm kê/điều chỉnh/nhập lại; không nhúng toàn bộ lịch sử tồn vào HangHoa. |
| TonKho/BienDongKho | TonKho theo kho-product; ledger sự kiện collection riêng, có source document và balance_after. |
| CongNo/ThanhToan/Credit | Nghĩa vụ nợ, payments, credits/movements có định danh và thời điểm riêng; không chỉ lưu tổng nợ cuối. |
| Shipment/LoTrinh/Handovers | Shipment và run độc lập; history bàn giao ưu tiên collection riêng, không ghi đè object một lần. |
| DoiTra/TraNCC | Aggregate trạng thái độc lập; có thể nhúng thông tin kiểm tra/thu hồi hữu hạn, tham chiếu restock/shipment/settlement. |
| BaoCaoThongKe | Giữ metadata duyệt, period, filters và snapshot có chính sách kích thước. |
| TaiKhoan và các profile | Một nguồn auth/role chuẩn; profile tùy chọn, không sao chép password/quyền thành nhiều nguồn. |

Không nhúng mọi payment của một khách hàng, mọi movement của một product hoặc mọi order vào một account. MongoDB có giới hạn BSON document 16 MiB và mảng tăng vô hạn là rủi ro cần tránh. [Hướng dẫn unbounded arrays](https://www.mongodb.com/docs/manual/data-modeling/design-antipatterns/unbounded-arrays/).

### 5.2 Quy tắc ID và cấu trúc item dự thảo

Đề xuất giữ numeric _id cho chứng từ để giảm thay đổi URL/cart/form; mã nghiệp vụ riêng có unique index. Item nhúng dùng item_id, tránh nhập nhằng với mapping id/_id của driver. Ví dụ KHÁI NIỆM sau không phải migration hay code đã triển khai:

```text
DonHang:
  _id: 123                         # định danh nội bộ số
  code: DH-2026-000123              # mã hiển thị, unique
  customer_id: 45                  # ID tài khoản khách hàng chuẩn
  quote_id: 18 | null
  contract_id: 9 | null
  status: awaiting_contract
  items:
    - item_id: 501                 # ổn định kể cả sắp xếp mảng
      product_id: 7
      quantity: Decimal128("0.125")
      unit_price: Decimal128("280000.00")
      line_total: Decimal128("35000.00")
  total: Decimal128("35000.00")
  version: 1
```

Name vật lý có thể giữ orders/items/product_id trong code, và ghi bảng ánh xạ sang tên tiếng Việt trong báo cáo. Đổi toàn bộ physical names sang DonHang/ChiTietDonHang là lựa chọn khác cần chốt, không nên vừa dịch tên vừa đổi cấu trúc một cách ngầm định.

### 5.3 Bảng mapping từ SQL hiện tại, không bỏ sót dữ liệu

| SQL nguồn | Đích/chiến lược dự kiến |
| --- | --- |
| users, customers | TaiKhoan/users chuẩn + profile khách hàng, mapping role quản lý/nhân viên |
| categories | DanhMucHangHoa/categories |
| products | HangHoa/products |
| suppliers | NhaCungCap/suppliers |
| warehouses | Kho/warehouses, cần bổ sung |
| inventory | TonKho/inventory, cần bổ sung |
| price_history, catalog_changes | LichSuGia và DeXuatDanhMuc riêng |
| estimation_norms | DinhMucDuToan riêng |
| quotes + quote_items | BangBaoGia + items nhúng |
| orders + order_items | DonHang + items nhúng |
| contracts | HopDong + snapshot tùy chính sách chốt |
| purchase_orders + purchase_items | DonDatNCC/DeXuatNhap + ordered items nhúng |
| stock_documents + stock_document_items | Chứng từ kho theo type + items nhúng; receipt có thể dùng PhieuNhapKho như logical name |
| stock_movements | BienDongKho riêng |
| supplier_incidents | SuVuNhapHang riêng hoặc nhúng dưới đúng receipt/purchase có ID và lịch sử |
| debts, payments | CongNo và ThanhToan riêng |
| customer_credits, customer_credit_movements | Credit và GiaoDichCredit riêng |
| invoices | HoaDon, giữ order reference và số tiền đã thu khi xuất |
| shipments, delivery_runs, handovers | Shipment, LoTrinh và lịch sử bàn giao |
| returns, supplier_returns | DoiTra và TraNCC |
| reports | BaoCaoThongKe với metadata/filter/snapshot |
| password_reset_tokens, sessions | Store Mongo tương thích auth/session; không nhập phiên/OTP cũ |
| cache, cache_locks, jobs, job_batches, failed_jobs | Driver Mongo tương ứng; không phát lại job cũ khi copy |

Toàn bộ 39 bảng nguồn được bao phủ ở mức mapping nhóm. Đây chưa là quyết định phải tạo số collection tương ứng; schema vật lý và index list phải được chốt sau.

## 6. Cần sửa gì trong kế hoạch trước đây

Khi bạn đồng ý phương án hybrid, cập nhật lại hai file thiết kế/kế hoạch cũ rồi mới bắt đầu lập trình:

1. Bỏ giả định “mọi bảng có collection riêng” cho quote/order/purchase/stock detail. Thêm module xử lý embedded items có ID ổn định và predicate theo parent.
2. Sửa importer: root trước, child gom vào đúng parent; giữ item_id cũ, source key mapping và không nhân đôi khi chạy lại.
3. Sửa truy vấn: các join tới items chuyển thành unwind/filter/project theo document, bảo toàn flat alias cho view/form/report; giữ batch/index cho references khác.
4. Counter cho root và item phải có mapping; insertGetId trên collection item cũ không còn được giữ nguyên.
5. Xác định nơi lưu contract snapshot và report snapshot, lifecycle/dung lượng; không dual-write bản chi tiết độc lập dễ lệch.
6. Cập nhật test fixtures và assertions: kiểm tra embedded child đúng parent, không chỉ assert một collection child cũ.
7. Giữ nguyên các điều kiện bắt buộc về Decimal128, session/queue/cache, quyền sở hữu, concurrency/rollback và nguyên tắc bản SQL không đổi.
8. Chỉ thêm profile/đổi tên physical schema nếu được chốt; không tự biến đợt đổi database thành viết lại toàn bộ auth.
9. Trước triển khai cần một physical schema có kiểu, defaults/nulls, references, indexes, cardinality và một ma trận 39 bảng -> vị trí dữ liệu đích cuối cùng.

Bản kế hoạch cũ được đánh dấu “cần chốt lại sau PDF” để tránh thực hiện nhầm giả định.

## 7. Các lựa chọn cần bạn chốt

Đề xuất của mình được ghi sẵn; chưa có lựa chọn nào dưới đây được xem là bạn đã đồng ý.

- [ ] Dùng hướng hybrid: nhúng item của chứng từ, bổ sung collection còn thiếu cho chức năng hiện tại; không ép đúng 15 collection.
- [ ] Giữ numeric ID public và stable item_id; mã Ma... là mã nghiệp vụ riêng.
- [ ] Giữ một tài khoản chuẩn theo 7 role hiện tại; các hộp KhachHang/NhanVien/BanQuanLy là mapping profile/role, không viết lại auth ngoài phạm vi.
- [ ] Tiền/lượng dùng Decimal128 + decimal string/BCMath; giữ lịch sử event và snapshot đã duyệt.
- [ ] Giữ physical names dễ tương thích code, sơ đồ/report dùng tên tiếng Việt với bảng mapping; nếu muốn tên vật lý theo PDF thì chốt riêng.
- [ ] Có chuyển toàn bộ dữ liệu thật hay chỉ tạo dữ liệu demo; bao gồm quyết định riêng cho upload/hợp đồng.
- [ ] Thư mục project mới, URL repo mới và MongoDB local replica set hay Atlas được cung cấp trước thực hiện.

## 8. Test nghiệm thu sau khi chốt

1. 72 hành vi nền chạy trên Mongo thật và có driver-real tests cho auth/session/cache/queue; không dựa kết quả SQLite.
2. Embedded item sau thêm/bớt/sort vẫn cập nhật đúng item_id, không sửa item ngoài parent/customer.
3. Quote -> order -> contract -> invoice -> release -> shipment đúng liên kết, role và trạng thái.
4. Tồn theo kho, số lượng lẻ, stocktake stale, hai request giữ/xuất hàng cùng lúc và initial inventory cạnh tranh.
5. Nhận 4+6 trên đơn 10, receipt/payable tương ứng, rollback khi lỗi giữa chừng.
6. Thanh toán nhiều lần, credit dùng đồng thời, hoàn/cấn trừ và báo cáo cuối kỳ trước/sau các sự kiện.
7. Giao thất bại/giao lại giữ đủ handover; đổi hàng thiếu tồn tạo restock trong một transaction, không lỗi nested.
8. Báo cáo đã duyệt/export giữ số liệu cũ khi giá/số dư hiện tại thay đổi; snapshot/chứng từ lớn được kiểm soát kích thước.
9. Import chạy lại không tạo trùng, IDs/links/hash mật khẩu/decimal/date/null khớp; source SQL và repo cũ không bị sửa.

## 9. Trạng thái cuối phiên review

Đã tạo ghi chú này và đánh dấu lại hai tài liệu kế hoạch ngoài repo. Không sửa PDF, không sửa code, không copy project, không cài dependency/driver, không chuyển dữ liệu và không tạo/push repo mới. Chỉ bắt đầu thực hiện sau khi người dùng chốt thiết kế/phạm vi và các đầu vào cần thiết.
