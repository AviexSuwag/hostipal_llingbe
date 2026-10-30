ALTER TABLE `billing_statement`
    ADD COLUMN `philhealth_deduction_percent` decimal(7,4) NOT NULL DEFAULT 0.0000 AFTER `philhealth_deduction`,
    ADD COLUMN `insurance_coverage_percent` decimal(7,4) NOT NULL DEFAULT 0.0000 AFTER `insurance_coverage_amount`,
    ADD COLUMN `hmo_discount_percent` decimal(7,4) NOT NULL DEFAULT 0.0000 AFTER `hmo_discount`,
    ADD COLUMN `government_discount_percent` decimal(7,4) NOT NULL DEFAULT 0.0000 AFTER `government_discount`;

UPDATE `billing_statement` bs
LEFT JOIN `admission` a ON a.admission_id = bs.admission_id
LEFT JOIN `patient` p ON p.patient_id = a.patient_id
SET bs.philhealth_deduction_percent = CASE
        WHEN bs.subtotal_amount > 0 THEN LEAST(100, ROUND(bs.philhealth_deduction / bs.subtotal_amount * 100, 4))
        ELSE 0
    END,
    bs.insurance_coverage_percent = CASE
        WHEN bs.subtotal_amount > 0 THEN LEAST(100, ROUND(bs.insurance_coverage_amount / bs.subtotal_amount * 100, 4))
        ELSE 0
    END,
    bs.hmo_discount_percent = CASE
        WHEN bs.subtotal_amount > 0 THEN LEAST(100, ROUND(bs.hmo_discount / bs.subtotal_amount * 100, 4))
        ELSE 0
    END,
    bs.government_discount_percent = CASE
        WHEN p.senior_id_type IS NOT NULL AND p.senior_id_type <> ''
         AND p.senior_id_number IS NOT NULL AND p.senior_id_number <> '' THEN 20.0000
        WHEN bs.subtotal_amount > 0 THEN LEAST(100, ROUND(bs.government_discount / bs.subtotal_amount * 100, 4))
        ELSE 0
    END;
