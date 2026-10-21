# Đối chiếu báo cáo Phase 5 và bản MongoDB đã triển khai

Ngày đối chiếu: 19/10/2026.

## 1. Phạm vi và cách hiểu

- Báo cáo gốc: [Group1_Quan_ly_cua_hang_vat_lieu_xay_dung_phase5.pdf](D:/Group1_Quan_ly_cua_hang_vat_lieu_xay_dung_phase5.pdf), 152 trang; trọng tâm mục 3.2 trang 76-77 và sơ đồ lớp trang 90. Số trang dưới đây là số trang PDF.
- Mã nguồn MongoDB đã xuất bản: commit `42f7b22d567c5fc6c120a9e636970caebdb77577`, repo `nhattoan235/SaiGon-CMC-MongoDB`, nhánh `main`.
- Đối chiếu code trước chuyển đổi: nhánh Toan, commit `f9860db0e594aefb8b2bb86ae81bb2306086ac17` của bản Laravel/MySQL.
- **Chưa sửa trực tiếp PDF gốc.** Các thay đổi dưới đây đã nằm trong code/tài liệu Markdown, hoặc được đánh dấu rõ là phần PDF cần cập nhật.
- “Bổ sung so với sơ đồ báo cáo” không đồng nghĩa “viết mới nghiệp vụ”: nhiều chức năng đã có trong bản SQL và được giữ lại khi chuyển MongoDB.
- File thống kê này được tạo sau lần push đã được cho phép; chưa commit hoặc push file này.

## 2. Những điểm giữ theo hướng thiết kế của báo cáo

1. Giữ mô hình document MongoDB: nhúng dữ liệu chi tiết cùng vòng đời chứng từ, tham chiếu tới thực thể độc lập.
2. Giữ danh mục, hàng hóa, nhà cung cấp, khách hàng, báo giá, đơn hàng, hợp đồng, hóa đơn, công nợ và báo cáo.
3. Giữ mã nghiệp vụ riêng với định danh nội bộ: mã đơn/mã hàng/mã hợp đồng không thay vai trò của `_id`.
4. Giữ luồng nghiệp vụ và giao diện ứng dụng SQL hiện có; không viết lại toàn bộ chức năng hoặc phân quyền chỉ để đổi database.

## 3. Bảng thay đổi so với báo cáo

Mã F01-F16 liên kết với [review trước triển khai](report-review.md). Trạng thái “đã triển khai” là trạng thái code, không có nghĩa hình hoặc chữ trong PDF đã được sửa.

| Mã | Trong báo cáo hiện tại | Bản MongoDB đã thực hiện | Phân loại và phần cần cập nhật báo cáo |
| --- | --- | --- | --- |
| F01 | Trang 77: mũi tên `HopDong.MaQL` tới `KhachHang`; trang 90 mô tả MaQL là BanQuanLy. Các định danh người thao tác chưa thống nhất. | Dùng ID tài khoản `users` cho người lập/duyệt và kiểm tra role theo chức năng; không tạo hai nguồn định danh người thao tác. | Chốt thiết kế tham chiếu. Cần vẽ lại mũi tên và đổi mô tả role/profile trong PDF; chưa sửa hình gốc. |
| F02 | Trang 77 chưa ghi rõ chuỗi sở hữu và liên kết khách hàng - báo giá - đơn - hợp đồng - hóa đơn. | Giữ các reference `customer_id`, `quote_id`, `order_id`, `contract_id` và quan hệ theo schema hiện có. `customer_id` ở chứng từ là tài khoản khách hàng trong users; profile customers liên kết user_id. | Bổ sung đặc tả so với sơ đồ; giữ nghiệp vụ SQL. Cần ghi rõ đích tham chiếu, nullable và cardinality. |
| F03 | Trang 77 có `HangHoa.SoLuong`, chưa đủ thể hiện tồn từng kho; nghiệp vụ kho đã mô tả ở các trang khác. | Giữ `warehouses`, `inventory`, `stock_documents.items`, `stock_movements`; tồn chuẩn theo cặp kho/sản phẩm. Có unique index và guard giao dịch trên document dùng chung. | Bổ sung mô hình kho so với sơ đồ, chuyển lưu trữ nghiệp vụ đã có. Không chỉ lưu một tổng tồn trên product. |
| F04 | `PhieuNhapKho` và một `BienBanNhapHang` chưa thể hiện đầy đủ đơn mua được duyệt và nhiều lần nhận. | Giữ `purchase_orders.items` tách với từng `stock_documents` nhận thực tế; lưu lượng đã nhận, supplier/kho và công nợ theo nguồn. Không unique debts.purchase_order_id. | Bổ sung ranh giới đơn mua/lần nhận; giữ luồng nhập nhiều đợt đã có ở SQL. |
| F05 | `CongNo.TongNo` chưa thể hiện đủ các khoản phải thu/phải trả, thanh toán từng lần và credit. | Giữ `debts`, `payments`, `customer_credits`, `customer_credit_movements`, gồm nguồn nghĩa vụ, amount/paid, phương thức, thời điểm và lịch sử. | Bổ sung mô hình tài chính so với sơ đồ; giữ nghiệp vụ SQL, không chỉ chuyển số dư cuối. |
| F06 | Trang 77 có biên bản sự vụ nhưng chưa đủ vòng đời đổi/trả; quy trình nằm ở trang 35-36, 72-73. | Giữ `returns`, `supplier_returns`, `supplier_incidents` cùng references tới đơn/dòng hàng, mua bù, shipment thay thế và đối soát. Thêm guard trên đơn khi kiểm tra hạn mức trả. | Giữ và chuyển nghiệp vụ; chỉnh bảo vệ giao dịch khi trả hàng. Cần bổ sung aggregate đổi/trả vào sơ đồ. |
| F07 | `LenhXuat` đang gom khái niệm lệnh xuất, trừ tồn và vận chuyển. | Giữ riêng điều kiện kế toán cho phép xuất, chứng từ kho, `shipments` và `delivery_runs`; bảo toàn dữ liệu tải trọng/thể tích đang dùng. | Làm rõ ranh giới dữ liệu; giữ luồng vận chuyển SQL. Không tạo một collection LenhXuat tương ứng một-một với hộp trong hình. |
| F08 | Biên bản bàn giao/sự vụ dạng object đơn có thể không thể hiện lịch sử giao lại. | Giữ `handovers` là lịch sử riêng theo shipment; shipment_id không unique, nên nhiều lần bàn giao được lưu riêng. | Bổ sung mô tả lịch sử vào sơ đồ; không ghi đè biên bản cũ khi giao lại. |
| F09 | Trang 90 có lượng kiểu Int và tiền kiểu Double; một số ngày biểu diễn String. | Lưu tiền/lượng bằng BSON Decimal128 từ chuỗi; scale theo field (thường tiền 2, lượng 3, một số định mức/thể tích 4). PHP dùng decimal string/BCMath. Thời điểm là BSON UTCDateTime, ngày thuần YYYY-MM-DD. | Chỉnh đặc tả kiểu vật lý và code chuyển đổi. Cần cập nhật Int/Double và quy ước ngày ở sơ đồ lớp. |
| F10 | Trang 77 có mảng chi tiết nhưng chưa đặc tả định danh từng dòng. | Root nghiệp vụ dùng `_id` số; item dùng `item_id` số ổn định, có counter riêng, giữ ID SQL cũ. Sửa/xóa không dựa vào vị trí mảng. | Bổ sung định danh và mapper cho embedded items. Cần thêm item_id, quy tắc cập nhật và bảo toàn parent. |
| F11 | Giá hiện tại và giá chứng từ, chi tiết hợp đồng và chi tiết đơn có phần lặp chưa rõ nguồn chuẩn. | Giữ giá/lượng giao dịch trên dòng báo giá/đơn; hợp đồng tham chiếu đơn và hồ sơ riêng, không tạo một bản contract_items chỉnh độc lập. Tổng tiền vẫn do nghiệp vụ server xử lý. | Chốt nguồn chuẩn, giữ tính toán SQL. **Chưa bổ sung snapshot tên/quy cách/đơn vị cho mọi chứng từ hoặc một bộ contract_items snapshot mới.** Nếu báo cáo mô tả những snapshot đó thì cần sửa lại hoặc triển khai riêng. |
| F12 | Trang 77 tách TaiKhoan/KhachHang/NhanVien/BanQuanLy; trang 54-55 đăng ký bằng số điện thoại. | Giữ `users` làm nguồn auth theo 7 role, giữ hash mật khẩu, trạng thái/khóa đăng nhập và profile customers. Chuyển User/provider sang Mongo, hỗ trợ ID số. Giữ đăng ký email + OTP đang có trong code SQL. | Chuyển kỹ thuật auth; không viết lại nghiệp vụ đăng ký. Cần đồng bộ PDF với email + OTP và mô hình tài khoản thực tế; không khẳng định code mới đổi số điện thoại thành email. |
| F13 | Trang 76-77 chưa đặc tả transaction, unique/partial indexes, xử lý đồng thời và default/null. | Thêm schema BSON/index, native transaction trên replica set, shared-document `_lock_version`, cấp ID nguyên tử; tách helper để tránh nested transaction ở mua bù. Mặc định retry cả transaction tối đa 5 lần bằng driver. | Bổ sung triển khai tính nhất quán MongoDB. Có kiểm thử nhiều session, lỗi giữa chừng, rollback và đọc lại số dư sau retry. |
| F14 | `BaoCaoThongKe` ở trang 77 chỉ có mã/loại/ngày/người thao tác; kỳ, lọc và snapshot chưa thể hiện. | Giữ metadata kỳ/lọc/trạng thái/duyệt và snapshot trong `reports`; sửa tương thích truy vấn ngày, tổng decimal, lọc nhà cung cấp và hình dạng dữ liệu export. | Bổ sung đặc tả dữ liệu báo cáo; giữ chức năng báo cáo SQL. Chưa có cơ chế chia snapshot lớn thành chunks. |
| F15 | `HangHoa.MaNCC` thể hiện một NCC; chưa ghi rõ lịch sử giá, duyệt danh mục và định mức. | NCC thực tế thuộc từng đơn mua/nhận; giữ `price_history`, `catalog_changes`, `estimation_norms`, các field active/minimum_stock/weight/volume/direct_purchase theo schema nguồn. | Bổ sung mapping cho dữ liệu SQL đã có; không tự thêm quan hệ một sản phẩm chỉ được nhập từ một NCC. |
| F16 | Trang 75, 151 còn gọi mô hình quan hệ; trang 59-60 hậu điều kiện “đã ký” chưa phân biệt duyệt/ký; trang 65 có cập nhật đơn giá trong use case báo cáo. | Tài liệu MongoDB mới mô tả lưu trữ hiện hành và đánh dấu tài liệu SQL lịch sử. Giữ phân biệt trạng thái hợp đồng theo code; không thêm sửa giá vào module báo cáo. | **Cần chỉnh chữ/đặc tả trong PDF. Chưa sửa trực tiếp các trang này.** |

## 4. Cấu trúc nhúng thực tế khác sơ đồ thế nào?

| Nội dung | Báo cáo trang 77 | Bản triển khai |
| --- | --- | --- |
| Dòng báo giá | ChiTietBaoGia[] | quotes.items[] |
| Dòng đơn bán | ChiTietDonHang[] | orders.items[] |
| Dòng đơn mua | Chưa có aggregate riêng rõ ràng | purchase_orders.items[] |
| Dòng phiếu nhập/xuất/kiểm kê | ChiTietNhapKho[] trong phiếu nhập | stock_documents.items[], phân biệt bằng loại chứng từ |
| Chi tiết hợp đồng | ChiTietHopDong[] riêng | Không tạo mảng chi tiết chỉnh độc lập; dùng liên kết tới đơn và hồ sơ hợp đồng |
| Biên bản nhận/sự vụ/bàn giao | Một số object nhúng trong phiếu/lệnh | Lưu chứng từ, sự vụ, lần giao và lịch sử bàn giao theo các collection/references phục vụ nghiệp vụ hiện có |

Schema hiện hành có **32 tên dữ liệu nghiệp vụ logic, lưu trong 28 collection nghiệp vụ vật lý**; 4 tên chi tiết được ánh xạ vào mảng của cha. Ngoài ra có runtime stores của Laravel và metadata `_counters`, `_snapshot_imports`, `_legacy_runtime_archive`. Không so “28” với “15” như một phép cộng đơn giản: một số hộp trong báo cáo được hợp nhất theo tài khoản/role, một số khái niệm được tách ra để giữ đầy đủ vòng đời.

Tên vật lý dùng tiếng Anh (`orders`, `products`, ...), không đổi mọi collection sang tên tiếng Việt như hình. Xem [mapping đầy đủ](physical-design.md) và [schema từng field](../../laravel/config/mongodb-schema.php).

## 5. Những phần kỹ thuật đã thêm/chỉnh mà báo cáo chưa đặc tả

| Hạng mục | Thay đổi thực tế | Bằng chứng chính |
| --- | --- | --- |
| Driver và kết nối | Thêm mongodb/laravel-mongodb 5.11.0 và thư viện liên quan từ lockfile; kết nối mặc định MongoDB, User dùng driver Mongo | composer.json/lock, config/database.php, app/Models/User.php |
| Truy vấn | Thêm Builder/Connection/DocumentStore chuyển tập thao tác ứng dụng sang aggregation/lookup/unwind native; từ chối SQL/biểu thức chưa hỗ trợ | app/Mongo/Builder.php, Connection.php, DocumentStore.php |
| Embedded writes | Upsert/ignore nguyên tử, typed ID, cập nhật/xóa đúng dòng, không làm duplicate-key abort transaction theo cách cũ | DocumentStore và các test mapper/query |
| Migration/schema | Thêm migration BSON hiện hành, validators/default/type/index; SQL migrations chuyển sang thư mục lưu tham khảo | database/migrations, database/sql-migrations, config/mongodb-schema.php |
| Auth và framework stores | NumericUserProvider; native session/cache/lock/queue/batch/failed-job/reset-password; sửa config/provider tương ứng | app/Mongo/NumericUserProvider.php, MongoServiceProvider, config và MongoFrameworkStoresTest |
| Tìm kiếm | Khóa dẫn xuất _search_* và collation cho so sánh/unique mã, username/email phù hợp dữ liệu hiện có | Schema, DocumentStore, User; test phân biệt hoa/thường/dấu |
| Import toàn bộ dữ liệu | Công cụ snapshot chỉ đọc, SHA256, dry-run/import/verify, từ chối ghi đè business target, chạy lại cùng snapshot không nhân đôi | tools/export-mysql-snapshot.php, MysqlSnapshot, cmms:import-mysql-snapshot |
| Dữ liệu runtime cũ | Giữ toàn bộ row trong archive, không tái đăng nhập session/OTP cũ hoặc phát lại job cũ | _legacy_runtime_archive và importer tests |
| Môi trường độc lập | PHP extension local; MongoDB riêng port27028/replica set cmcMongoRs; setup/start/status/stop, không sửa php.ini toàn máy | tools/setup-mongodb-runtime.ps1, mongodb-local.ps1, php-mongodb.cmd |
| Kiểm thử và CI | Chuyển bộ test sang MongoDB thật, thêm test hồi quy, native stores, migration, import, concurrency, retry và rollback; thêm workflow CI | tests/ và .github/workflows/mongodb.yml |
| Tài liệu | Review, physical design, ledger, runbook, README chạy dự án và mẫu .env | docs/mongodb/, README.md, laravel/README.md, laravel/.env.example |

Các hạng mục trên là thay đổi lưu trữ/tương thích. Không nên viết trong báo cáo rằng toàn bộ mua hàng, giao nhận, đổi trả, dự toán và báo cáo đều là chức năng mới được tạo trong đợt chuyển MongoDB.

## 6. Bằng chứng triển khai và chuyển dữ liệu

| Chỉ tiêu | Kết quả đã ghi nhận |
| --- | --- |
| Bộ kiểm thử cuối | 110 tests, 1.060 assertions; 0 errors, 0 failures, 0 skipped trên MongoDB thật |
| So với baseline SQL | Giữ 72 bài kiểm thử gốc; tổng số tăng thêm 38 bài/trường hợp trong bộ test |
| Snapshot nguồn | 40 bảng, 342 row, gồm cả bảng rỗng trong manifest |
| Dữ liệu nghiệp vụ nhập | 303 row logic, gồm các dòng chi tiết đã nhúng |
| Runtime nguồn lưu archive | 39 row: 30 migrations, 9 sessions; các runtime tables khác của snapshot rỗng |
| Tham chiếu | Đối chiếu 592 giá trị; nguồn/đích đều 0 orphan |
| Tổng decimal | 45 tổng theo field khớp chính xác |
| Upload riêng tư | 4 file có SHA256 khớp nguồn và đích |
| HTTP kiểm tra công khai | /, /login, /products, /cart và 10 CSS/JS assets trả 200 |
| Cài đặt lại | Setup chạy lại thành công, composer.lock không đổi |
| Bản SQL gốc | Giữ nguyên HEAD f9860db, working tree sạch trong lần kiểm chứng |

Đây là kết quả nghiệm thu được lưu của đợt triển khai, không có nghĩa đã chạy test mới trong lần lập thống kê này. Bằng chứng chi tiết nằm ở `.runtime/final-suite.xml`, `.runtime/final-suite.log`, `.runtime/data-audit.json` và các manifest riêng tư trên máy; không đưa backup/credentials/dữ liệu riêng lên GitHub.

## 7. Những phần chưa làm hoặc cần nói rõ trong báo cáo

1. **PDF gốc chưa được chỉnh sửa**, gồm sơ đồ collection, sơ đồ lớp, đặc tả đăng ký, trạng thái ký và thuật ngữ quan hệ/NoSQL.
2. Không tạo đầy đủ BanQuanLy/NhanVien như hai collection profile riêng; User và role đang là mô hình thực thi chuẩn.
3. Không thêm một bản chi tiết hợp đồng độc lập hoặc snapshot tên/quy cách/đơn vị cho mọi chứng từ; giá/lượng giao dịch đã giữ theo nghiệp vụ hiện có.
4. MongoDB không có SQL foreign key/cascade. Validator/index kiểm kiểu/ràng buộc tương ứng; references phụ thuộc workflow và kiểm chứng. Không mô tả chúng như FK tự cưỡng chế của MySQL.
5. Collation ICU/khóa tìm kiếm là phép gần đúng với MySQL unicode_ci; không cam kết mọi trường hợp Unicode có thứ tự/đẳng thức giống hệt.
6. Document BSON có giới hạn 16 MiB; chưa làm chia nhỏ tự động items/report snapshots. Không mô tả đã có chunking.
7. Replica set local một node phục vụ phát triển/test, không có HA; chưa triển khai production hoặc nghiệm thu tải lớn.
8. Workflow CI đã thêm và kiểm tra cấu hình tĩnh; không gọi đó là CI GitHub đã chạy thành công nếu chưa có run thực tế.
9. Không bổ sung kế toán thuế, lương, tích hợp MISA/Fast hoặc chức năng ngoài phạm vi chuyển đổi này.

## 8. Các vị trí nên cập nhật PDF

- **Trang 75 và 151:** thống nhất đoạn mô tả kết quả hiện hành là CSDL NoSQL MongoDB; giữ từ “quan hệ” chỉ khi đang nói về bản cũ để so sánh.
- **Mục 3.2, trang 76-77:** thêm schema vật lý, mapping 28 collection nghiệp vụ/4 nhóm items nhúng; ownership/references, ID, unique/partial indexes, validators, transaction và runtime stores.
- **Sơ đồ trang 77:** sửa mũi tên người quản lý/người lập; thêm nhóm kho, đơn mua/nhận nhiều đợt, payments/credit, đổi trả, shipments/runs/handovers, báo cáo snapshot; thể hiện nguồn auth thống nhất.
- **Trang 90:** phân biệt mô hình lớp nghiệp vụ với vị trí lưu vật lý; cập nhật số lượng/tiền/ngày, ID item và các quan hệ phù hợp schema thực tế.
- **Trang 54-55:** đồng bộ đặc tả đăng ký email + OTP với ứng dụng đang chạy.
- **Trang 59-60:** tách rõ duyệt hợp đồng với xác nhận đã ký; hậu điều kiện phụ thuộc nhánh xử lý.
- **Trang 65:** tách cập nhật đơn giá khỏi luồng lập báo cáo, hoặc mô tả rõ quan hệ tới module danh mục.
- **Phần triển khai/kiểm thử:** ghi cách migration, bảo toàn dữ liệu/hash, topology MongoDB và các chỉ tiêu nghiệm thu nêu ở mục 6; nêu giới hạn ở mục 7.

## 9. Đoạn mô tả có thể dùng để cập nhật báo cáo

> Hệ thống được hiện thực hóa trên Laravel và MongoDB theo mô hình hybrid. Các dòng chi tiết báo giá, đơn hàng, đơn mua và chứng từ kho được nhúng trong document cha; các thực thể độc lập và sổ phát sinh được lưu ở những collection tham chiếu. Mô hình vật lý gồm 28 collection nghiệp vụ, giữ định danh số và mã nghiệp vụ riêng, sử dụng Decimal128 cho tiền và số lượng. Những nghiệp vụ tác động nhiều document dùng transaction trên replica set và cơ chế kiểm soát xung đột. Dữ liệu từ bản SQL được chuyển qua snapshot nhất quán, kiểm tra checksum và đối chiếu từng trường; dữ liệu runtime cũ được lưu riêng để tránh kích hoạt lại phiên hoặc job. Kết quả nghiệm thu của đợt triển khai ghi nhận 110 bài kiểm thử đạt với 1.060 assertions trên MongoDB, đồng thời bảo toàn 303 bản ghi nghiệp vụ, 39 bản ghi runtime và 4 tệp đính kèm của snapshot nguồn.
