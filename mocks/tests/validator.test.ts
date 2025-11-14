import assert from "node:assert";
import { validateAndRoute, type ClaimPayload } from "../validator.ts";

function test(name: string, fn: () => void) {
    try { fn(); console.log(`✓ ${name}`); } catch (e) { console.error(`✗ ${name}`); throw e; }
}

// 1) FR, TGV, 45 min → voucher-only → operator portal
test("FR TGV 45min -> voucher-only route", () => {
    const res = validateAndRoute({
        country: "FR",
        segments: [{ product: "TGV INOUI", train_no: "TGV 8451" }],
        delayMinutesAtFinal: 45,
        dep_date: "2025-11-10",
        dep_time: "09:30",
        arr_time: "11:00",
        actual_arr_time: "11:45",
    } satisfies ClaimPayload);
    assert.strictEqual(res.accept, false);
    if (!res.accept) {
        assert.strictEqual(res.route, "operatorPortal");
        assert.ok(res.reason.includes("Voucher-only"));
    }
});

// 2) FR IC 125 min → cash ok → G30 map
test("FR Intercités 125min -> accept FR map", () => {
    const res = validateAndRoute({
        country: "FR",
        segments: [{ product: "Intercités", train_no: "IC 1234" }],
        delayMinutesAtFinal: 125,
        dep_date: "2025-11-10",
        dep_time: "14:10",
        arr_time: "17:20",
        actual_arr_time: "19:25",
        iban: "FR7630006000011234567890189",
        bic: "AGRIFRPP",
    } satisfies ClaimPayload);
    assert.strictEqual(res.accept, true);
    if (res.accept) {
        assert.strictEqual(res.formMap, "map_fr_sncf_g30.json");
        assert.strictEqual(res.addAnnexArt9, true);
    }
});

// 3) DE RE 62 min → cash ok → DB map
test("DE REG 62min -> accept DB map", () => {
    const res = validateAndRoute({
        country: "DE",
        segments: [{ product: "RE", train_no: "RE 12345" }],
        delayMinutesAtFinal: 62,
        dep_date: "2025-11-09",
        dep_time: "7:5",
        arr_time: "08:50",
        actual_arr_time: "09:52",
        iban: "DE89370400440532013000",
        bic: "COBADEFFXXX",
    } satisfies ClaimPayload);
    assert.strictEqual(res.accept, true);
    if (res.accept) {
        assert.strictEqual(res.country, "DE");
        assert.strictEqual(res.formMap, "map_de_db_fahrgastrechte.json");
        assert.strictEqual(res.normalized.payment_bank_transfer, true);
        assert.strictEqual(res.normalized.payment_voucher, false);
    }
});

// 4) IT Frecciarossa 70 min → cash ok → IT map
test("IT Frecciarossa 70min -> accept IT map", () => {
    const res = validateAndRoute({
        country: "IT",
        segments: [{ product: "FRECCIAROSSA", train_no: "FR 9999" }],
        delayMinutesAtFinal: 70,
        dep_date: "2025-11-10",
    } satisfies ClaimPayload);
    assert.strictEqual(res.accept, true);
    if (res.accept) {
        assert.strictEqual(res.formMap, "map_it_trenitalia_compensation.json");
    }
});

// 5) FR TER regional -> block EU flow
test("FR TER -> operator portal (regional block)", () => {
    const res = validateAndRoute({
        country: "FR",
        segments: [{ product: "TER", train_no: "TER 1234" }],
        delayMinutesAtFinal: 90,
        dep_date: "2025-11-10",
    } satisfies ClaimPayload);
    assert.strictEqual(res.accept, false);
    if (!res.accept) {
        assert.strictEqual(res.route, "operatorPortal");
        assert.ok(res.productHints?.includes("regional_block"));
    }
});

console.log("All validator tests passed.");
