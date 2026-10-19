# Thiết kế vật lý MongoDB

Trạng thái: thiết kế hybrid được chấp thuận cho bản độc lập `Sai-Gon-CMC-Laravel-MongoDB`, theo [implementation ledger](implementation-ledger.md). [Bản review Phase 5](report-review.md) là bằng chứng lịch sử trước phê duyệt; các checkbox/trạng thái dự thảo trong đó không thay quyết định đã chấp thuận. Tên collection vật lý dùng tiếng Anh để giữ giao diện nghiệp vụ hiện có.

Nguồn chi tiết có thể thực thi: [`mongodb-schema.php`](../../laravel/config/mongodb-schema.php), [`Schema`](../../laravel/app/Mongo/Schema.php), [`DocumentStore`](../../laravel/app/Mongo/DocumentStore.php), [`Builder`](../../laravel/app/Mongo/Builder.php). File schema ghi từng field, kiểu, nullable/default, primary key, foreign reference và index; bảng dưới đây xác định nơi dữ liệu SQL được lưu cuối cùng.

## Ranh giới document và ánh xạ đầy đủ

| Bảng SQL nguồn | Collection/document đích | Quy tắc |
| --- | --- | --- |
| users | users | Tài khoản và bảy role hiện có là nguồn auth chuẩn; numeric `_id`, giữ password hash/status. |
| customers | customers | Profile, `user_id` tham chiếu users; không là nguồn password/role thứ hai. |
| categories | categories | Danh mục, `parent_id` tùy chọn. |
| products | products | Danh mục/giá hiện tại; không ghi đè giá snapshot đã chấp nhận trên chứng từ. |
| suppliers | suppliers | Nhà cung cấp độc lập. |
| warehouses | warehouses | Kho độc lập. |
| inventory | inventory | Một document cho mỗi cặp warehouse/product. |
| price_history | price_history | Lịch sử giá. |
| catalog_changes | catalog_changes | Đề nghị/thẩm định thay đổi danh mục. |
| estimation_norms | estimation_norms | Định mức theo loại công trình/product. |
| quotes | quotes | Báo giá, customer và salesperson references. |
| quote_items | quotes.items[] | Mỗi dòng có stable `item_id`; parent reference được suy ra từ quote root. |
| orders | orders | Đơn hàng và references customer/quote/contract. |
| order_items | orders.items[] | Stable `item_id`, product, lượng, giá giao dịch và số đã xử lý. |
| contracts | contracts | Chứng từ/hồ sơ hợp đồng, references và đường dẫn file riêng tư; không tạo bản chi tiết độc lập có thể lệch orders.items. |
| purchase_orders | purchase_orders | Đơn mua/phê duyệt; giữ supplier/warehouse references. |
| purchase_items | purchase_orders.items[] | Stable `item_id`, lượng đặt và lượng nhận lũy kế. |
| stock_documents | stock_documents | Mỗi lần nhập/xuất/kiểm kê là chứng từ riêng; tham chiếu đơn mua/đơn bán/kho. |
| stock_document_items | stock_documents.items[] | Các dòng thực tế thuộc đúng một chứng từ kho. |
| stock_movements | stock_movements | Lịch sử biến động riêng, giữ thời điểm và nguồn chứng từ. |
| supplier_incidents | supplier_incidents | Sự vụ có IDs/references tới đơn mua/dòng mua và người xử lý. |
| debts | debts | Công nợ phải thu/phải trả từng nguồn, không gộp mất lịch sử. |
| payments | payments | Từng sự kiện thanh toán, debt/recorder references. |
| customer_credits | customer_credits | Credit phát sinh, tham chiếu customer/return. |
| customer_credit_movements | customer_credit_movements | Lịch sử tạo/dùng/hoàn credit. |
| invoices | invoices | Chứng từ hóa đơn nội bộ theo order. |
| shipments | shipments | Lần/chặng giao, order/return/delivery_run references. |
| delivery_runs | delivery_runs | Chuyến/lộ trình, có thể phục vụ nhiều shipment. |
| handovers | handovers | Nhiều biên bản trên một shipment; không ghi đè lần giao thất bại. |
| returns | returns | Aggregate đổi/trả, lượng/trạng thái và references mua bù/giao thay thế. |
| supplier_returns | supplier_returns | Trả NCC và kết quả xử lý/đối soát riêng. |
| reports | reports | Kỳ/filter/trạng thái/người duyệt và snapshot báo cáo. |
| migrations | _legacy_runtime_archive | Giữ nguyên từng row nguồn; migration của bản MongoDB dùng migrations riêng. |
| sessions | _legacy_runtime_archive | Giữ payload nguồn nhưng không khôi phục đăng nhập cũ. |
| password_reset_tokens | _legacy_runtime_archive | Giữ bản sao, không kích hoạt OTP/token cũ. |
| cache | _legacy_runtime_archive | Giữ bản sao thay vì dùng lại giá trị cache cũ. |
| cache_locks | _legacy_runtime_archive | Không khôi phục lock nguồn vào runtime mới. |
| jobs | _legacy_runtime_archive | Không phát lại job nguồn. |
| job_batches | _legacy_runtime_archive | Lưu lịch sử nguồn, không tái kích hoạt batch. |
| failed_jobs | _legacy_runtime_archive | Lưu payload nguồn, không retry tự động. |

Snapshot thực tế ngày 18/10/2026 có **40 bảng, 342 row**: 303 business rows và 39 runtime rows (30 migrations, 9 sessions). Con số 39 bảng trong tài liệu review ban đầu là khảo sát cũ; manifest thực tế là chuẩn kiểm kê. Bảng rỗng vẫn có tên/schema/hash trong manifest. Các collection runtime hoạt động mới được tạo theo driver MongoDB cho sessions, password_reset_tokens, cache/cache_locks, jobs/job_batches/failed_jobs; chúng độc lập với archive nguồn.

Metadata bổ sung: `_counters` cấp ID, `_snapshot_imports` theo dõi snapshot/import/verify, `_legacy_runtime_archive` chứa `{source_table, source_position, snapshot, payload}`. Chúng không thay thế các business collections.

## Kiểu và định danh

- Business root dùng numeric `_id` giữ nguyên ID SQL; business code là field khác có unique index. Counter riêng theo logical table, nâng tới ít nhất ID lớn nhất đã import. Một số framework stores có natural key string hoặc định dạng do driver quy định; không ép tất cả khóa runtime thành số.
- Các array items dùng `item_id` numeric ổn định, giữ ID dòng SQL cũ và có counter riêng cho dòng mới. Vị trí trong array không là ID. Khi đọc, mapper trả lại `id` và parent ID để giữ form/route hiện có; khi sửa/xóa dùng đúng parent và item_id.
- Tiền/lượng lưu BSON Decimal128 được tạo từ decimal string, chuẩn hóa precision/scale theo từng field: tiền thường scale 2, lượng scale 3, định mức/thể tích theo schema scale 4. Mapper trả decimal string về PHP và nghiệp vụ dùng BCMath; không đi qua binary float để tính tiền.
- Thời điểm lưu BSON UTCDateTime; khi đọc, đổi về timezone ứng dụng và format cũ. Ngày thuần giữ `YYYY-MM-DD`. Giữ null như null, boolean như boolean và JSON object/array như BSON; không biến null thành chuỗi rỗng.
- Tên/mã có các field `_search_*` dẫn xuất cho tìm kiếm; `_lock_version` là guard nội bộ. Các field này không phải dữ liệu nguồn nghiệp vụ mới.

## Quan hệ, index và tính nhất quán

`Schema::create` tạo/cập nhật BSON validators và indexes từ schema. Business fields bắt buộc, nullable và kiểu được kiểm bằng validator; bố cục một số native framework stores được driver sở hữu. Foreign references có index nhưng MongoDB không cung cấp SQL foreign key: workflow/mapper và kiểm chứng dữ liệu phải duy trì liên kết, quyền sở hữu và xóa/cập nhật phù hợp.

Các ràng buộc nổi bật:

| Collection | Khóa/index quan trọng |
| --- | --- |
| users | Unique username/email/phone; field nullable dùng partial indexes theo metadata. |
| inventory | Unique `(warehouse_id, product_id)`, index từng reference. |
| categories/products/suppliers/warehouses và chứng từ có code | Unique business code theo metadata. |
| quotes/orders/purchase_orders/stock_documents | Unique `items.item_id` giữa các root, index `items.product_id`; mapper ngăn trùng item ID trong cùng parent. |
| orders/contracts/invoices | Unique quan hệ một-một và code theo schema; nullable relation dùng partial index. |
| estimation_norms | Unique `(building_type, product_id)`. |
| handovers | Index shipment_id **không unique** để giữ nhiều lần bàn giao. |
| debts | purchase_order_id **không unique** để hỗ trợ nhiều lần nhận/phải trả. |
| customer_credits/supplier_returns | Unique return_id theo quy trình hiện có. |
| sessions/cache/cache_locks | TTL `expires_at` khi driver dùng ngày hết hạn; quá hạn vẫn phải được kiểm bởi driver. |

Một business command dùng native transaction/session trên replica set. Former `lockForUpdate` được ánh xạ thành write guard trên cùng document chia sẻ, thay vì đọc rồi giả định đã khóa. Counter/item/stock/reservation/credit thay đổi tham gia cùng session khi nằm trong transaction. Các nhánh nhận hàng, trả hàng/mua bù cần dùng transaction ngoài cùng thay vì tạo nested transaction. Retry phải do tầng transaction có hiểu lỗi MongoDB xử lý; không retry tùy ý một phần nghiệp vụ đã commit.

`App\Mongo\Builder` giữ tập thao tác query thực sự được ứng dụng dùng và chuyển thành aggregation/lookup/unwind phía MongoDB. Nó không là SQL parser tổng quát; connection từ chối chạy SQL. Mở rộng query vocabulary phải thêm kiểm thử tương ứng. So sánh/sắp xếp dùng ICU collation vi strength 1; LIKE tìm tên/mã dùng khóa ASCII dẫn xuất. Đây là phép gần đúng so với MySQL unicode_ci, không là cam kết mọi edge case Unicode có thứ tự/đẳng thức giống hệt nguồn; cần test dữ liệu đặc thù trước mở rộng tìm kiếm.

## Snapshot, lịch sử và dung lượng

Dòng chứng từ giữ giá/lượng giao dịch; thay đổi catalog không thay lịch sử đã chấp nhận. Payments, movements, handovers, credits và returns là các luồng lịch sử riêng, không nhúng mọi sự kiện vào một customer/product. Báo cáo giữ snapshot và metadata duyệt hiện có. Chứng từ upload giữ đường dẫn relative tới storage riêng tư và được kiểm bằng manifest SHA256.

BSON document có giới hạn 16 MiB, bao gồm cả items và report snapshot. Không bổ sung mảng lịch sử tăng vô hạn; kiểm kích thước trước khi mở rộng dữ liệu lớn. Nếu một báo cáo/chứng từ có thể vượt giới hạn, cần thiết kế chunks/file snapshot có manifest và transaction/lifecycle rõ ràng trước khi dùng, không cắt bỏ dữ liệu âm thầm. [Giới hạn BSON chính thức](https://www.mongodb.com/docs/manual/reference/limits/#bson-document-size).

## Môi trường và kiểm chứng

Local dùng một node `cmcMongoRs`, `127.0.0.1:27028`, data dưới `.runtime/`; không sửa PHP/MongoDB/MySQL của bản nguồn. Một node hỗ trợ transaction cho phát triển nhưng không cung cấp khả năng chịu lỗi nhiều node. Triển khai thật dùng URI xác thực/TLS và replica set vận hành riêng. [Hướng dẫn replica set chính thức](https://www.mongodb.com/docs/manual/tutorial/convert-standalone-to-replica-set/).

Nghiệm thu gồm field/count/archive verification của snapshot, quan hệ và số dư, password hashes, upload hashes, hành vi role/auth, decimal/date/null, rollback và cạnh tranh trên replica set thật. Toàn bộ PHPUnit chạy MongoDB; không dùng kết quả SQLite làm bằng chứng chuyển đổi. Full-suite cuối đã pass 110 tests / 1060 assertions trên MongoDB thật sau Pint; snapshot/import/audit/HTTP evidence được ghi trong [ledger](implementation-ledger.md). Xem [runbook](migration-runbook.md) cho lệnh và giới hạn kiểm chứng; hosted GitHub CI chưa được chạy.
