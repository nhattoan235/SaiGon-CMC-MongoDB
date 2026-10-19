# Tài liệu MongoDB

Bản MongoDB độc lập là hướng triển khai hiện hành của clone này. Thư mục Laravel/MySQL gốc được giữ riêng, không thay database hoặc cấu hình nguồn. Không push hoặc thêm remote khi chưa có chỉ dẫn mới.

- [Setup/chạy ứng dụng](../../README.md)
- [Thiết kế vật lý hiện hành](physical-design.md): hybrid, ID số, Decimal128, mapping toàn bộ snapshot và index.
- [Migration runbook](migration-runbook.md): snapshot chỉ đọc, import/archive, upload, verification và phục hồi.
- [Implementation ledger](implementation-ledger.md): quyết định đã chấp thuận và gates cần có bằng chứng.
- [CI replica set](../../.github/workflows/mongodb.yml): chạy PHPUnit đầy đủ trên MongoDB.

## Tài liệu lịch sử

[Review Phase 5](report-review.md) ghi nhận khảo sát và đề xuất **trước khi phê duyệt**; giữ nguyên để truy vết, không coi trạng thái chưa duyệt trong file đó là trạng thái hiện hành. Các file trong [AI_context](../../AI_context/README.md), `docs/superpowers/` và `laravel/database/sql-migrations/` được giữ từ bản SQL để tra cứu nghiệp vụ/lịch sử. Những lệnh MySQL, số bảng khảo sát cũ hoặc kết quả test cũ trong đó không phải hướng dẫn runtime hay bằng chứng kiểm thử MongoDB. Dùng README, physical design, runbook và kết quả kiểm chứng hiện tại của bản clone.
