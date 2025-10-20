# Art. 9 Implementering – Status, Analyse og Anbefalinger

Dato: 2025-10-14
Forfatter: Automatisk genereret opsummering

## 1. Formål
Dette dokument giver et samlet overblik over den nuværende (delvise) implementering af informationsforpligtelser efter Art. 9 i (recast) EU-forordningen om jernbanepassagerrettigheder, som den fremgår i denne kodebase, samt anbefalede forbedringer for at komme tættere på fuld overensstemmelse.

## 2. Centrale Kodekomponenter
| Fil | Rolle |
|-----|------|
| `src/Service/Art9Evaluator.php` | Evaluering af informations-hooks og samlet compliance (`art9_ok`). |
| `src/Service/ExemptionProfileBuilder.php` | Bygger fritagelsesprofil; i dag kun samlet `art9` boolean (ingen delstykker). |
| `config/data/exemption_matrix.json` | Indeholder potentielle undtagelser på land/scope. Kan nævne delstykker som "Art.9(2)" men de mappes ikke særskilt. |
| `DemoController` & `ComputeController` | Kører evalueringer for demo / API. |

## 3. Nuværende Funktionalitet (Kort)
`Art9Evaluator` indsamler 5 hooks:
- `info_before_purchase`
- `info_on_rights`
- `info_during_disruption`
- `language_accessible`
- `accessibility_format`

Logik:
1. Henter fritagelsesprofil. Hvis `profile.articles.art9 === false` ⇒ retur med `art9_ok = null` + note (undtaget).
2. Markerer `art9_ok = false` hvis nogen hook er eksplicit "Nej".
3. Markerer `art9_ok = true` hvis ingen hook er "Nej" og ingen er `unknown`.
4. Ellers `art9_ok = null` (ufuldstændig / ukendt).
5. Fallback-anbefalinger genereres for ikke-"Ja" værdier.

## 4. Data & Fritagelser
- `exemption_matrix.json` kan indeholde undtagelser som "Art.9(2)", men disse genkendes ikke særskilt: kun 
  - `Art.9` (fuld) vil sætte `articles['art9']=false`.
- Resultat: Delvise undtagelser (kun af stykke 2 eller 3) differentieres ikke.

## 5. Identificerede Begrænsninger
| Område | Aktuel Tilstand | Konsekvens |
|--------|-----------------|------------|
| Delstykker (9(1), 9(2), 9(3)) | Ikke særskilt modelleret | Kan ikke vise granular compliance eller selective exemptions. |
| Matrix parsing | Kun fuld "Art.9" | Delvise fritagelser ignoreres → mulig over-/under-angivelse. |
| Hooks dækning | Minimal (5 styk) | Mangler kontaktinfo, visning, frekvens, kanaler, onboard/station skiltning, rettigheder notice. |
| Reasoning granularitet | Samlet reasoning-liste | Bruger kan ikke se per-del status. |
| Fallback-strategi | Generiske anbefalinger | Ingen målrettet guidance pr. delstykke. |
| Test-invariants | Ikke formaliseret | Risiko for regressions ved udvidelse. |

## 6. Foreslået Udvidelsesplan
### Fase 1 (Lav risiko – struktur)
- Udvid `ExemptionProfileBuilder` til at mappe: `Art.9(1)`, `Art.9(2)`, `Art.9(3)` → `profile['articles_sub'] = { art9_1: bool, art9_2: bool, art9_3: bool }`.
- Definér `profile['articles']['art9'] = false` kun hvis alle tre er false ELLER eksplicit `Art.9` findes.
- Tilføj note for delvise undtagelser.

### Fase 2 (Hooks & Evaluering)
Tilføj nye hooks i `Art9Evaluator` (alle default `unknown`):
- Struktur 9(1): `info_before_purchase`, `language_accessible`, `accessibility_format`, `multi_channel_information`, `accessible_formats_offered`.
- Struktur 9(2): `info_on_rights`, `rights_notice_displayed`, `rights_contact_provided`.
- Struktur 9(3): `info_during_disruption`, `station_board_updates`, `onboard_announcements`, `disruption_updates_frequency`, `assistance_contact_visible`.

Evaluer per del:
```
part_ok = null
if nogen = "Nej" → false
else if ingen = unknown → true
```
Samlet `art9_ok = false` hvis én del = false og del ikke exempt; hvis alle ikke-fritagne dele = true → true; ellers null.

### Fase 3 (Fallbacks & UI)
- Kortlæg fallback-rekommandationer pr. del (fx manglende rights notice → `show_basic_rights_link`).
- Generér `ui_banners` (valgfrit) for hver del med non-compliance eller exemption.

### Fase 4 (Tests)
- Opret `tests/TestCase/Service/Art9EvaluatorTest.php` med cases:
  1. All Ja → art9_ok = true.
  2. En del med Nej (9(2)) → art9_ok = false, parts korrekt.
  3. En del unknown → art9_ok = null.
  4. Delvis exemption (kun 9(2) exempt) → parts rapporterer kun 9(1), 9(3).

### Fase 5 (Docs & API)
- Dokumentér nyt outputskema (parts + samlet felt) i README / API reference.

## 7. Forslået Outputstruktur (Efter Fase 2)
```json
{
  "hooks": { ... },
  "parts": {
    "art9_1_ok": true,
    "art9_2_ok": false,
    "art9_3_ok": null
  },
  "art9_ok": false,
  "missing": ["rights_contact_provided","disruption_updates_frequency"],
  "reasoning": [
    "rights_contact_provided mangler.",
    "info_on_rights mangler."
  ],
  "fallback_recommended": [
    "show_basic_rights_link",
    "prompt_contact_point"
  ],
  "ui_banners": [
    "Art. 9(2) ikke opfyldt — vis klagevejledning."
  ]
}
```

## 8. Test-Invariants (Kan bruges i automatiske asserts)
1. Hvis `profile.articles.art9 === false` ⇒ `art9_ok === null` og alle parts (hvis tilstede) sættes til null.
2. Hvis en del `art9_X_ok === false` ⇒ `art9_ok === false` (medmindre hele delstykke er exempt – i så fald null).
3. Ingen del må være `true` hvis en af dens hooks er `Nej`.
4. Hooks med værdi `unknown` må ikke give delstatus = true.
5. Fallback `show_basic_rights_link` altid hvis `info_on_rights != "Ja"` og del ikke exempt.

## 9. Risikovurdering
| Ændring | Risiko | Mitigation |
|---------|--------|------------|
| Tilføj sub-articles i profil | Lav | Isoler i nyt `articles_sub`. |
| Flere hooks i evaluator | Lav-middel | Bevar bagudkompatible felter (`art9_ok`). |
| UI ændringer | Middel | Feature-flag eller version-tag i API. |

## 10. Trin-for-Trin Patch Udkast (Kernetiltag)
(High-level – klar til implementering når ønsket)
1. Profil: parse matrix entries, når `exemptions` indeholder strenge der matcher regex `^Art\.9\((1|2|3)\)$`.
2. Init `articles_sub = ['art9_1'=>true,'art9_2'=>true,'art9_3'=>true]`.
3. For hver exemption match: set den specifikke til false; hvis generisk `Art.9` → alle tre false.
4. Efter loop: sæt `articles.art9 = (art9_1||art9_2||art9_3)` ellers false.
5. Evaluator: beregn parts med grouped hook-sæt.

## 11. Aktuel Status (Efter Seneste Session)
- Ingen kodeændringer foretaget i `Art9Evaluator` endnu – kun analyseret.
- Fritagelsesprofil uændret (ingen sub-stykker).
- Dokument (denne fil) tilføjet under `docs/` for reference.

## 12. Hurtigt Resume til Stakeholders
Art. 9 understøttes i dag kun på et simpelt samlet niveau med 5 generelle indikatorer og hel-fritagelse. Delvise undtagelser (9(1)/(2)/(3)) og mere granulære informationskanaler håndteres ikke. Dette dokument beskriver en lav-risiko migreringsvej til fuld granularitet uden at break'e eksisterende klienter.

## 13. Næste Handling (Hvis accepteret)
- Godkend Fase 1–2 → implementér patches.
- Tilføj testfil → kør CI.
- Opdater API docs / README.

---
Har du brug for at jeg udfører Fase 1 med det samme, kan jeg lave patch på få minutter.
