# Ferry + Multimodal Implementation Spec

Date: 2026-03-12

Purpose:
- prepare ferry implementation without breaking the current rail flow
- keep `TRIN 2` multimodal and shared across transport modes
- make fixtures/scenarios part of the design from the start
- defer mobile changes until backend and flow contracts are stable

## 1. Core architecture rule

The system must separate:

1. contract responsibility / claim channel
2. material passenger rights

This means:

- `TRIN 2` decides:
  - contract structure
  - primary claim party
  - affected segment
  - active rights module
- `TRIN 5` decides:
  - incident facts
  - legal gates for the selected module
- downstream steps are opened by gates, not by hardcoded transport-specific assumptions

## 2. Shared multimodal fields

These fields should become the common output contract for `TRIN 2`, session state, fixture payloads and scenario output:

```json
{
  "transport_mode": "rail|ferry|bus|air",
  "service_type": "passenger_service|cruise",
  "contract_topology": "single_mode_single_contract|protected_single_contract|single_multimodal_contract|separate_contracts|unknown_manual_review",
  "seller_type": "operator|ticket_vendor|travel_agent|tour_operator|unknown",
  "seller_name": "",
  "shared_booking_reference": true,
  "single_transaction": true,
  "contract_structure_disclosure": "bundled|separate|none|unknown",
  "separate_contract_notice": "yes|no|unclear",
  "incident_segment_mode": "rail|ferry|bus|air|unknown",
  "incident_segment_operator": "",
  "primary_claim_party": "seller|carrier|segment_operator|manual_review",
  "rights_module": "rail|ferry|bus|air|unknown",
  "manual_review_required": false
}
```

## 3. `TRIN 2` design

### 3.1 Shared purpose

`TRIN 2` should no longer be treated as a rail-only Art. 12 step in the UI.

UI wording should instead describe:
- ticket and booking structure
- single vs separate contracts
- who sold the journey
- where the problem occurred

Recommended title:

- `Billet, kontraktstruktur og ansvar`

### 3.2 Shared questions

The multimodal `TRIN 2` should collect:

1. who sold the journey
2. whether there is one shared booking reference / PNR / reservation reference
3. whether all tickets were bought in one commercial transaction
4. whether the journey contains one segment, same-mode connections, or multimodal connections
5. whether the passenger was clearly informed before purchase that the journey consisted of separate contracts
6. whether tickets / booking confirmation explicitly state that the segments are separate contracts
7. where the problem occurred

### 3.3 Shared resolver stack

The coding target should be:

- `ContractTopologyResolver` (shared)
- `RailContractResolver`
- `FerryContractResolver`
- `BusContractResolver`
- `AirContractResolver`

`RailContractResolver` may integrate current Art. 12 logic.

## 4. Ferry scope model (`TRIN 2`)

### 4.1 Required scope inputs

```json
{
  "service_type": "passenger_service|cruise",
  "departure_port_in_eu": true,
  "arrival_port_in_eu": true,
  "carrier_is_eu": true,
  "departure_from_terminal": true,
  "vessel_passenger_capacity": 120,
  "vessel_operational_crew": 8,
  "route_distance_meters": 12000
}
```

### 4.2 Scope rules

The ferry regulation applies when:

- passenger service departs from an EU port, or
- passenger service departs outside the EU and arrives in the EU and is operated by an EU carrier, or
- cruise departs from an EU port

### 4.3 Scope exclusions

The implementation should encode these exclusions early:

- `vessel_passenger_capacity <= 12`
- `vessel_operational_crew <= 3`
- `route_distance_meters < 500`

Correction:
- the distance threshold must be `500 meters`, not `50`

### 4.4 Cruise carve-out

If `service_type = cruise`, branch early and disable the rules that do not apply.

At minimum, the design should support disabling:

- `art16_2`
- `art18`
- `art19`
- `art20_1`
- `art20_4`

### 4.5 Ferry scope output

```json
{
  "regulation_applies": true,
  "scope_exclusion_reason": null,
  "service_type": "passenger_service",
  "departure_from_terminal": true
}
```

## 5. Ferry `TRIN 5` incident inputs

Ferry incident capture should be kept mode-neutral in structure but ferry-specific in evaluator logic.

Recommended inputs:

```json
{
  "incident_type": "delay|cancellation",
  "expected_departure_delay_90": false,
  "actual_departure_delay_90": true,
  "arrival_delay_minutes": 130,
  "scheduled_journey_duration_minutes": 300,
  "overnight_required": false,
  "informed_before_purchase": false,
  "passenger_fault": false,
  "weather_safety": false,
  "extraordinary_circumstances": false
}
```

Important distinction:

- Art. 17 and 18 are triggered by cancellation or departure delay
- Art. 19 is triggered by delayed arrival

## 6. Ferry gate model

Recommended ferry gate outputs:

```json
{
  "gate_art16_notice": true,
  "gate_art16_alt_connections": true,
  "gate_art17_refreshments": true,
  "gate_art17_hotel": false,
  "gate_art18": true,
  "gate_art19": true,
  "art19_comp_band": "25",
  "gate_manual_review": false
}
```

### 6.1 Art. 16

Add explicit information gates:

- `gate_art16_notice`
- `gate_art16_alt_connections`

This should not be collapsed into compensation logic.

### 6.2 Art. 17

Split Art. 17 into:

- `gate_art17_refreshments`
- `gate_art17_hotel`

Reasons:

- the hotel branch is subject to a weather safety exclusion
- the refreshments branch is not identical to the overnight branch

### 6.3 Art. 18

Activate on:

- cancellation
- expected departure delay >= 90 min
- actual departure delay >= 90 min

### 6.4 Art. 19

Do not model Art. 19 as boolean-only logic.

Required logic:

- based on `arrival_delay_minutes`
- measured against `scheduled_journey_duration_minutes`
- returns:
  - `none`
  - `25`
  - `50`

Thresholds:

- up to 4 hours scheduled duration -> 1 hour delay = 25%
- more than 4 but under 8 hours -> 2 hours delay = 25%
- 8 to under 24 hours -> 3 hours delay = 25%
- 24+ hours -> 6 hours delay = 25%
- double threshold -> 50%

### 6.5 Art. 20 exceptions

Model these separately and explicitly:

- informed before purchase / passenger fault
  - disables Art. 17 and Art. 19
  - does not automatically disable Art. 18
- weather safety
  - disables Art. 17(2) hotel
  - disables Art. 19
- extraordinary circumstances
  - disables Art. 19

### 6.6 Open ticket rule

The design must support:

- open ticket without specified departure time disables Art. 17, 18 and 19
- unless it is a season pass / period card type case

## 7. Ferry step visibility

After ferry `TRIN 5`, the UI should open the next step based on gates:

- show information panel if `gate_art16_notice = true`
- open assistance step if:
  - `gate_art17_refreshments = true`
  - or `gate_art17_hotel = true`
- open rerouting/refund step if `gate_art18 = true`
- open result/compensation step if:
  - `gate_art19 = true`
  - or claim-assist/data-pack must still be produced
- route to manual review if:
  - `manual_review_required = true`
  - or the scope/contract/segment data is unclear

## 8. Fixtures and scenarios

Fixtures and scenarios must become multimodal now, not later.

### 8.1 Shared scenario shape

All scenarios should support:

- `transport_mode`
- `contract_meta`
- `scope_meta`
- `incident_meta`
- `expected`

### 8.2 Example ferry scenario

```json
{
  "id": "ferry-direct-delay-90-terminal",
  "transport_mode": "ferry",
  "contract_meta": {
    "contract_topology": "single_mode_single_contract",
    "seller_type": "operator",
    "seller_name": "Example Ferry",
    "shared_booking_reference": true,
    "single_transaction": true,
    "contract_structure_disclosure": "bundled",
    "separate_contract_notice": "no",
    "incident_segment_mode": "ferry",
    "incident_segment_operator": "Example Ferry",
    "primary_claim_party": "seller",
    "rights_module": "ferry",
    "manual_review_required": false
  },
  "scope_meta": {
    "service_type": "passenger_service",
    "departure_port_in_eu": true,
    "arrival_port_in_eu": true,
    "carrier_is_eu": true,
    "departure_from_terminal": true,
    "vessel_passenger_capacity": 200,
    "vessel_operational_crew": 10,
    "route_distance_meters": 10000
  },
  "incident_meta": {
    "incident_type": "delay",
    "actual_departure_delay_90": true,
    "arrival_delay_minutes": 140,
    "scheduled_journey_duration_minutes": 300,
    "overnight_required": false,
    "informed_before_purchase": false,
    "passenger_fault": false,
    "weather_safety": false,
    "extraordinary_circumstances": false
  },
  "expected": {
    "regulation_applies": true,
    "gate_art17_refreshments": true,
    "gate_art17_hotel": false,
    "gate_art18": true,
    "gate_art19": true,
    "art19_comp_band": "25",
    "rights_module": "ferry"
  }
}
```

### 8.3 First ferry scenario set

Minimum set:

- `ferry-direct-delay-90-terminal`
- `ferry-cancellation-reroute-refund`
- `ferry-weather-safety-no-hotel-no-art19`
- `ferry-extraordinary-no-art19`
- `ferry-open-ticket-no-departure-time`
- `ferry-season-pass-repeated-delay`
- `ferry-cruise-scope-carveout`
- `rail-ferry-single-multimodal-contract`
- `rail-ferry-separate-contracts`
- `rail-ferry-unknown-manual-review`

### 8.4 Rail compatibility

Current rail scenarios should remain valid by:

- adding `transport_mode = rail`
- mapping existing `art12_meta` into the shared `contract_meta` model gradually

## 9. Runner and mapper implications

### `SessionToFixtureMapper`

Should eventually emit:

- `transport_mode`
- `contract_meta`
- `scope_meta`
- `incident_meta`

### `ScenarioRunner`

Should conceptually execute in this order:

1. shared `ContractTopologyResolver`
2. ferry scope resolver
3. ferry contract resolver
4. ferry rights evaluator
5. shared claim/result mapping

### Expected assertions

Scenario output should be able to assert:

- `contract_topology`
- `primary_claim_party`
- `rights_module`
- `regulation_applies`
- `gate_art17_refreshments`
- `gate_art17_hotel`
- `gate_art18`
- `gate_art19`
- `art19_comp_band`
- `manual_review_required`

## 10. Mobile timing

Recommendation:

- do not update mobile now
- do account for mobile in the contract design now

Reason:

- backend and flow contracts must stabilize first
- mobile should only be updated once:
  - `transport_mode`
  - `contract_topology`
  - `rights_module`
  - ferry result payloads
  are stable

Implication:

- backend/flow/scenarios first
- mobile later

## 11. Corrections locked in this spec

These corrections should be treated as fixed design decisions:

- `route_distance_meters < 500`, not `< 50`
- split Art. 17 into refreshments and hotel
- calculate Art. 19 on delayed arrival, not generic delay
- require explicit `departure_from_terminal`
- branch early for `cruise`
- support the open-ticket exclusion

## 12. Coding order

Recommended implementation order:

1. add shared multimodal fields
2. make `TRIN 2` multimodal
3. implement ferry scope resolver
4. implement ferry contract resolver
5. implement ferry `TRIN 5` gating
6. implement ferry result mapping
7. add ferry fixtures/scenarios
8. run regression across rail + ferry
9. update mobile after contracts are stable

