<?php
/** @var \App\View\AppView $this */
/** @var array<int,array<string,mixed>> $railNav */
/** @var array<string,string> $quickLinks */

$this->assign('title', 'Færge');
$fullBleedLanding = !empty($fullBleedLanding);
$brandHref = (string)($brandHref ?? '/faerge');
$lang = strtolower((string)($lang ?? 'da'));
$langPreviewSuffix = $lang !== 'da' ? '&lang=' . rawurlencode($lang) : '';
$pageTranslations = (array)($pageTranslations ?? []);

if ($lang === 'en') {
    $pageTranslations += [
        'Igangværende rejse' => 'Ongoing journey',
        'Afsluttet rejse' => 'Completed journey',
        'Rejsen starter senere' => 'Journey starts later',
        'Live' => 'Live',
        'Indsend krav' => 'Submit claim',
        'Før afgang' => 'Before departure',
        'Start her' => 'Start here',
        'Færge entry · spring trin 1 over' => 'Ferry entry · skip step 1',
        'Afklar hurtigt,' => 'Quickly clarify,',
        'om din overfart giver ret til kompensation.' => 'whether your crossing entitles you to compensation.',
        'Vi går direkte til billet, afgang og hændelse. Derefter vurderer vi kompensation, assistance og refusion eller ombooking i færgesporet.' => 'We go directly to ticket, departure and incident. Then we assess compensation, assistance and refund or rerouting in the ferry track.',
        'Entitlements' => 'Entitlements',
        'Vælg afgang' => 'Choose departure',
        'Hændelse' => 'Incident',
        'Mad og hotel' => 'Meals and hotel',
        'Samtykke' => 'Consent',
        'Preview flows' => 'Preview flows',
        'Trin 1: Start' => 'Step 1: Start',
        'Trin 2: Vælg afgang' => 'Step 2: Choose departure',
        'Trin 3: Hændelse' => 'Step 3: Incident',
        'Trin 4: Refusion / ombooking' => 'Step 4: Refund / rerouting',
        'Trin 5: Assistance' => 'Step 5: Assistance',
        'Trin 6: Kontakttrin' => 'Step 6: Contact step',
        'Vælg rejsetype og gå direkte videre' => 'Choose journey type and continue directly',
        'Du står midt i en forsinkelse, aflysning eller terminalsituation nu. Gå direkte til hændelsen og få vurderet kompensation, assistance og ombooking.' => 'You are in the middle of a delay, cancellation or terminal situation right now. Go straight to the incident and get compensation, assistance and rerouting assessed.',
        'Rejsen er afsluttet. Rekonstruér hændelsen og se hurtigt, om du kan få kompensation og dækning.' => 'The journey is complete. Reconstruct the incident and quickly see whether you may receive compensation and coverage.',
        'Afgangen ligger foran dig. Brug dette spor ved varslet aflysning eller forventet forsinkelse før afgang.' => 'Departure is still ahead of you. Use this track for notified cancellation or expected delay before departure.',
        'Hvad kan du få?' => 'What can you get?',
        'Prøv et hurtigt færge-estimat' => 'Try a quick ferry estimate',
        'Juster forsinkelse og billetpris og se, hvordan kompensationen typisk bevæger sig mellem 0%, 25% og 50% i færgesporet.' => 'Adjust delay and ticket price and see how compensation typically moves between 0%, 25% and 50% in the ferry track.',
        'Estimeret kompensation' => 'Estimated compensation',
        '25% af 300 kr. ved 75 minutters forsinkelse' => '25% of DKK 300 at 75 minutes of delay',
        'Ingen kompensation' => 'No compensation',
        '25% af billetprisen' => '25% of the ticket price',
        '50% af billetprisen' => '50% of the ticket price',
        'Din forsinkelse' => 'Your delay',
        'Billetpris' => 'Ticket price',
        'Ingen kompensation under 60 minutters forsinkelse' => 'No compensation below 60 minutes of delay',
    ];
} elseif ($lang === 'fr') {
    $this->assign('title', 'Ferry');
    $pageTranslations += [
        'Igangværende rejse' => 'Trajet en cours',
        'Afsluttet rejse' => 'Trajet termine',
        'Rejsen starter senere' => 'Le trajet commence plus tard',
        'Live' => 'Live',
        'Indsend krav' => 'Envoyer la demande',
        'Før afgang' => 'Avant le depart',
        'Start her' => 'Commencez ici',
        'Færge entry · spring trin 1 over' => 'Entree ferry · sautez l etape 1',
        'Afklar hurtigt,' => 'Clarifiez rapidement,',
        'om din overfart giver ret til kompensation.' => 'si votre traversee donne droit a une compensation.',
        'Vi går direkte til billet, afgang og hændelse. Derefter vurderer vi kompensation, assistance og refusion eller ombooking i færgesporet.' => 'Nous allons directement au billet, au depart et a l incident. Ensuite nous evaluons compensation, assistance et remboursement ou reacheminement dans la piste ferry.',
        'Entitlements' => 'Droits',
        'Vælg afgang' => 'Choisir le depart',
        'Hændelse' => 'Incident',
        'Mad og hotel' => 'Repas et hotel',
        'Samtykke' => 'Consentement',
        'Preview flows' => 'Apercu des parcours',
        'Trin 1: Start' => 'Etape 1 : Depart',
        'Trin 2: Vælg afgang' => 'Etape 2 : Choisir le depart',
        'Trin 3: Hændelse' => 'Etape 3 : Incident',
        'Trin 4: Refusion / ombooking' => 'Etape 4 : Remboursement / reacheminement',
        'Trin 5: Assistance' => 'Etape 5 : Assistance',
        'Trin 6: Kontakttrin' => 'Etape 6 : Contact',
        'Vælg rejsetype og gå direkte videre' => 'Choisissez le type de trajet et continuez directement',
        'Du står midt i en forsinkelse, aflysning eller terminalsituation nu. Gå direkte til hændelsen og få vurderet kompensation, assistance og ombooking.' => 'Vous etes en ce moment au milieu d un retard, d une annulation ou d une situation en terminal. Allez directement a l incident et faites evaluer compensation, assistance et reacheminement.',
        'Rejsen er afsluttet. Rekonstruér hændelsen og se hurtigt, om du kan få kompensation og dækning.' => 'Le trajet est termine. Reconstituez l incident et voyez vite si vous pouvez obtenir compensation et prise en charge.',
        'Afgangen ligger foran dig. Brug dette spor ved varslet aflysning eller forventet forsinkelse før afgang.' => 'Le depart est encore devant vous. Utilisez cette piste en cas d annulation annoncee ou de retard attendu avant le depart.',
        'Hvad kan du få?' => 'Que pouvez-vous obtenir ?',
        'Prøv et hurtigt færge-estimat' => 'Essayez une estimation rapide pour le ferry',
        'Juster forsinkelse og billetpris og se, hvordan kompensationen typisk bevæger sig mellem 0%, 25% og 50% i færgesporet.' => 'Ajustez le retard et le prix du billet et voyez comment la compensation evolue en general entre 0 %, 25 % et 50 % dans la piste ferry.',
        'Estimeret kompensation' => 'Compensation estimee',
        '25% af 300 kr. ved 75 minutters forsinkelse' => '25 % de 300 DKK pour 75 minutes de retard',
        'Ingen kompensation' => 'Aucune compensation',
        '25% af billetprisen' => '25 % du prix du billet',
        '50% af billetprisen' => '50 % du prix du billet',
        'Din forsinkelse' => 'Votre retard',
        'Billetpris' => 'Prix du billet',
        'Ingen kompensation under 60 minutters forsinkelse' => 'Aucune compensation en dessous de 60 minutes de retard',
        'Tid til vurdering' => 'Temps jusqu a l evaluation',
        'Ca. 3 min' => 'Env. 3 min',
        'Til du når hændelsen' => 'Jusqu a l incident',
        'Vi vurderer' => 'Nous evaluons',
        '3 spor' => '3 pistes',
        'Kompensation, assistance og refusion / ombooking' => 'Compensation, assistance et remboursement / reacheminement',
        'Kommission' => 'Commission',
        'Kun hvis vi gennemfører kravet for dig' => 'Seulement si nous menons la reclamation a bien pour vous',
        'Det vurderer vi hurtigt' => 'Ce que nous evaluons rapidement',
        'Vi vurderer hurtigt, om du kan få kompensation og dækning for nødvendige udgifter under rejsen.' => 'Nous evaluons rapidement si vous pouvez obtenir une compensation et la prise en charge des depenses necessaires pendant le trajet.',
        '25% eller 50% af billetpris' => '25 % ou 50 % du prix du billet',
        'Mad, hotel og nødvendige udgifter under overfarten' => 'Repas, hotel et depenses necessaires pendant la traversee',
        'Ny overfart, pengene tilbage og nødvendige ombookingsudgifter' => 'Nouvelle traversee, remboursement et frais de rebooking necessaires',
        'Vores rolle' => 'Notre role',
        'Vi afklarer først dit færgespor og kan derefter føre kravet videre for dig' => 'Nous clarifions d abord votre parcours ferry et pouvons ensuite poursuivre la reclamation pour vous',
        'Vi tager 20% i kommission, hvis vi gennemfører kravet for dig.' => 'Nous prenons 20 % de commission si nous menons la reclamation a bien pour vous.',
        'Hvor hurtigt får jeg en vurdering?' => 'En combien de temps obtenez-vous une evaluation ?',
        'Du når normalt til hændelsen på cirka 3 minutter. Derfra kan vi hurtigt vurdere, om kompensation, assistance eller refusion / ombooking er relevant.' => 'Vous atteignez generalement l incident en environ 3 minutes. A partir de la, nous pouvons rapidement evaluer si une compensation, une assistance ou un remboursement / reacheminement est pertinent.',
        'Hvad vurderer I i færgesporet?' => 'Que verifiez-vous dans le parcours ferry ?',
        'Vi vurderer tre spor: kompensation, assistance og refusion / ombooking. Det betyder både selve billetkravet og nødvendige udgifter under rejsen.' => 'Nous evaluons trois pistes : compensation, assistance et remboursement / reacheminement. Cela couvre a la fois la reclamation liee au billet et les depenses necessaires pendant le trajet.',
        'Hvornår tager I 20% i kommission?' => 'Quand prenez-vous 20 % de commission ?',
        'Vi tager kun 20% i kommission, hvis vi gennemfører kravet for dig. Du betaler ikke kommission bare for at få den første vurdering i flowet.' => 'Nous prenons 20 % de commission uniquement si nous menons la reclamation a bien pour vous. Vous ne payez pas de commission seulement pour obtenir la premiere evaluation dans le flow.',
        'Dækker I hotel, mad og transport videre?' => 'Couvrez-vous aussi l hotel, les repas et le transport de continuation ?',
        'Ja, når færgereglerne åbner for assistance eller ombooking, vurderer vi også nødvendige udgifter som mad, hotel og videre transport.' => 'Oui, lorsque les regles ferry ouvrent le droit a l assistance ou au reacheminement, nous evaluons aussi les depenses necessaires comme les repas, l hotel et la continuation du trajet.',
        'Om os' => 'A propos',
        'Vi vurderer færgepassagerers rettigheder' => 'Nous evaluons les droits des passagers ferry',
        'TrainClaim hjælper med at afklare, om du kan få kompensation og dækning for nødvendige udgifter ved assistance, refusion eller ombooking.' => 'TrainClaim vous aide a determiner si vous pouvez obtenir une compensation et la prise en charge des depenses necessaires en cas d assistance, de remboursement ou de reacheminement.',
        'Vi går hurtigt til det afgørende' => 'Nous allons rapidement a l essentiel',
        'Landing-siden flytter rejsestatus op foran flowet. Derfor går du direkte videre til billet, afgang og hændelse, som afgør retningen i sagen.' => 'La page d entree place le statut du trajet avant le flow. Vous passez donc directement au billet, au depart et a l incident, qui determinent la direction du dossier.',
        '20% kun hvis vi gennemfører kravet' => '20 % uniquement si nous menons la reclamation a bien',
        'Du betaler ikke kommission for den første vurdering. Vi tager kun 20%, hvis vi gennemfører kravet for dig.' => 'Vous ne payez pas de commission pour la premiere evaluation. Nous prenons 20 % uniquement si nous menons la reclamation a bien pour vous.',
        'Kontakt' => 'Contact',
        'Har du spørgsmål før du går i gang, kan du skrive til os. Vi holder kontakten enkel på landing-siden, så fokus stadig er på at komme hurtigt frem til vurderingen.' => 'Si vous avez des questions avant de commencer, vous pouvez nous ecrire. Nous gardons le contact simple sur la landing page afin de conserver le focus sur une evaluation rapide.',
        'Vi svarer så hurtigt som muligt og hjælper dig videre til det rigtige færgespor.' => 'Nous repondons aussi vite que possible et vous aidons a poursuivre dans le bon parcours ferry.',
        'færgesager vurderet' => 'dossiers ferry evalues',
        'refusion, ombooking og assistance' => 'remboursement, reacheminement et assistance',
        'til hurtig vurdering' => 'pour une evaluation rapide',
        'Baseret paa passagerrettigheder til soes' => 'Base sur les droits des passagers maritimes',
    ];
}

$this->set('pageTranslations', $pageTranslations);

$travelCards = [
    [
        'title' => 'Igangværende rejse',
        'summary' => 'Du står midt i en forsinkelse, aflysning eller terminalsituation nu. Gå direkte til hændelsen og få vurderet kompensation, assistance og ombooking.',
        'badge' => 'Live',
        'badgeClass' => 'is-live',
        'href' => $quickLinks['ongoing'],
        'accentClass' => 'is-green',
        'icon' => 'live',
    ],
    [
        'title' => 'Afsluttet rejse',
        'summary' => 'Rejsen er afsluttet. Rekonstruér hændelsen og se hurtigt, om du kan få kompensation og dækning.',
        'badge' => 'Indsend krav',
        'badgeClass' => 'is-blue',
        'href' => $quickLinks['completed'],
        'accentClass' => 'is-blue',
        'icon' => 'file',
    ],
    [
        'title' => 'Rejsen starter senere',
        'summary' => 'Afgangen ligger foran dig. Brug dette spor ved varslet aflysning eller forventet forsinkelse før afgang.',
        'badge' => 'Før afgang',
        'badgeClass' => 'is-violet',
        'href' => $quickLinks['beforeStart'],
        'accentClass' => 'is-violet',
        'icon' => 'clock',
    ],
];

$previewCb = (string)time();
$tc6PreviewLinks = [
    'entitlements' => $this->Url->build('/tc6/ferry/entitlements') . '?cb=' . rawurlencode($previewCb) . $langPreviewSuffix,
    'departure' => $this->Url->build('/tc6/ferry/departure') . '?cb=' . rawurlencode($previewCb) . $langPreviewSuffix,
    'incident' => $this->Url->build('/tc6/ferry/incident') . '?cb=' . rawurlencode($previewCb) . $langPreviewSuffix,
    'remedies' => $this->Url->build('/tc6/ferry/remedies') . '?cb=' . rawurlencode($previewCb) . $langPreviewSuffix,
    'assistance' => $this->Url->build('/tc6/ferry/assistance') . '?cb=' . rawurlencode($previewCb) . $langPreviewSuffix,
    'contact' => $this->Url->build('/tc6/ferry/contact') . '?cb=' . rawurlencode($previewCb) . $langPreviewSuffix,
];
?>

<style>
  .tc-ferry {
    --tc-accent: #14b8a6;
    --tc-accent-soft: rgba(20, 184, 166, 0.18);
    --tc-accent-border: rgba(153, 246, 228, 0.42);
    --tc-accent-text: #99f6e4;
  }

  .tc-page {
    max-width: 1120px;
    margin: 0 auto;
    padding: 6px 16px 44px;
    font-family: 'DM Sans', 'Segoe UI', sans-serif;
    color: #0d1117;
  }

  .tc-page.tc-page--fullbleed {
    max-width: none;
    padding: 0;
  }

  .tc-page.tc-page--fullbleed > :not(.tc-hero) {
    max-width: 1120px;
    margin-left: auto;
    margin-right: auto;
    padding-left: 16px;
    padding-right: 16px;
    box-sizing: border-box;
  }

  .tc-hero {
    position: relative;
    overflow: hidden;
    background:
      radial-gradient(circle at 76% 18%, rgba(153, 246, 228, 0.18), transparent 28%),
      linear-gradient(180deg, #0f2530 0%, #10212c 56%, #0b1620 100%);
    border-radius: 24px;
    padding: 56px 28px 34px;
  }

  .tc-page.tc-page--fullbleed > .tc-hero {
    margin: 0;
    border-radius: 0;
    padding-left: max(24px, calc((100vw - 1120px) / 2 + 16px));
    padding-right: max(24px, calc((100vw - 1120px) / 2 + 16px));
    padding-top: 8px;
    padding-bottom: 92px;
  }

  .tc-page.tc-page--fullbleed > .tc-hero::after {
    content: "";
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 144px;
    background: linear-gradient(
      to bottom,
      rgba(15, 27, 45, 0) 0%,
      rgba(15, 27, 45, 0.14) 26%,
      rgba(255, 255, 255, 0.74) 72%,
      rgba(255, 255, 255, 1) 100%
    );
    filter: blur(10px);
    pointer-events: none;
    z-index: 0;
  }

  .tc-page.tc-page--fullbleed > .tc-hero > * {
    position: relative;
    z-index: 1;
  }

  .tc-hero-grid {
    display: grid;
    grid-template-columns: minmax(0, 1fr) minmax(300px, 460px);
    gap: 28px;
    align-items: center;
  }

  .tc-hero-3d {
    position: relative;
    width: 100%;
    height: 420px;
    border-radius: 16px;
    overflow: hidden;
  }

  .tc-hero-3d canvas {
    width: 100% !important;
    height: 100% !important;
    display: block;
  }

  .tc-pill {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-bottom: 26px;
    padding: 8px 16px;
    border: 1px solid var(--tc-accent-border);
    border-radius: 999px;
    background: var(--tc-accent-soft);
    color: var(--tc-accent-text);
    font-size: 12px;
    font-weight: 600;
  }

  .tc-pill::before {
    content: "";
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: var(--tc-accent);
  }

  .tc-hero h1 {
    margin: 0 0 22px;
    color: #fff;
    font-family: 'Syne', 'Segoe UI', sans-serif;
    font-size: clamp(42px, 6vw, 78px);
    line-height: 0.96;
    letter-spacing: -0.045em;
    font-weight: 800;
  }

  .tc-hero h1 span {
    display: block;
    color: #67e8f9;
  }

  .tc-hero-copy {
    font-size: 18px;
    line-height: 1.62;
    color: rgba(226, 232, 240, 0.84);
    max-width: 610px;
    margin: 0 0 28px;
  }

  .tc-hero-actions {
    display: flex;
    align-items: center;
    gap: 14px;
    flex-wrap: wrap;
  }

  .tc-preview-links {
    display: flex;
    align-items: center;
    gap: 10px;
    flex-wrap: wrap;
    margin-top: 16px;
  }

  .tc-preview-label {
    color: rgba(226, 232, 240, 0.56);
    font-size: 11px;
    font-weight: 700;
    letter-spacing: .12em;
    text-transform: uppercase;
  }

  .tc-preview-link {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    min-height: 36px;
    padding: 0 14px;
    border-radius: 999px;
    border: 1px solid rgba(255,255,255,0.14);
    background: rgba(255,255,255,0.03);
    color: rgba(226, 232, 240, 0.92);
    font-size: 13px;
    font-weight: 700;
    text-decoration: none;
    transition: transform .18s ease, border-color .18s ease, background .18s ease;
  }

  .tc-preview-link:hover {
    transform: translateY(-1px);
    border-color: rgba(153, 246, 228, 0.42);
    background: rgba(153, 246, 228, 0.10);
  }

  .tc-preview-link::before {
    content: "TC6";
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 28px;
    height: 20px;
    padding: 0 6px;
    border-radius: 999px;
    background: rgba(153, 246, 228, 0.14);
    color: #99f6e4;
    font-size: 10px;
    font-weight: 800;
    letter-spacing: .08em;
  }

  .tc-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    gap: 9px;
    min-height: 52px;
    padding: 0 22px;
    border-radius: 18px;
    font-size: 14px;
    font-weight: 700;
    text-decoration: none;
    transition: transform .18s ease, background .18s ease, border-color .18s ease;
  }

  .tc-btn:hover {
    transform: translateY(-1px);
  }

  .tc-btn-live {
    background: #16a34a;
    color: #fff;
    box-shadow: 0 10px 24px rgba(22, 163, 74, 0.2);
  }

  .tc-btn-secondary {
    color: #fff;
    background: rgba(255,255,255,0.02);
    border: 1px solid rgba(255,255,255,0.15);
  }

  .tc-live-dot {
    width: 9px;
    height: 9px;
    border-radius: 999px;
    background: #bbf7d0;
    box-shadow: 0 0 0 6px rgba(34, 197, 94, 0.16);
  }

  .tc-hero-visual {
    display: flex;
    align-items: center;
    justify-content: center;
    min-height: 340px;
  }

  .tc-hero-svg {
    width: 100%;
    height: auto;
    display: block;
    filter: drop-shadow(0 28px 40px rgba(7, 13, 28, 0.38));
  }

  .tc-hero-svg .tc-anim-train {
    animation: tc-train-cross 8s linear infinite;
    transform-origin: center;
    will-change: transform;
  }

  .tc-hero-svg .tc-anim-smoke-1 {
    animation: tc-smoke-rise-1 2.6s ease-out infinite;
    transform-origin: center;
    will-change: transform, opacity;
  }

  .tc-hero-svg .tc-anim-smoke-2 {
    animation: tc-smoke-rise-2 2.6s ease-out .32s infinite;
    transform-origin: center;
    will-change: transform, opacity;
  }

  .tc-hero-svg .tc-anim-smoke-3 {
    animation: tc-smoke-rise-3 2.6s ease-out .64s infinite;
    transform-origin: center;
    will-change: transform, opacity;
  }

  .tc-hero-svg .tc-anim-light {
    animation: tc-light-pulse 3.8s ease-in-out infinite;
    transform-origin: center;
    will-change: opacity, transform;
  }

  .tc-hero-svg .tc-anim-headlight {
    animation: tc-headlight-pulse 2.2s ease-in-out infinite;
    transform-origin: center;
    will-change: opacity, transform;
  }

  .tc-arrow-wrap {
    display: flex;
    justify-content: center;
    margin-top: -18px;
    margin-bottom: 18px;
    position: relative;
    z-index: 1;
  }

  .tc-arrow {
    width: 48px;
    height: 48px;
    border-radius: 999px;
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.12);
    box-shadow: 0 10px 22px rgba(15, 23, 42, 0.12);
    display: inline-flex;
    align-items: center;
    justify-content: center;
    color: #0f172a;
    font-size: 24px;
  }

  .tc-label {
    font-size: 12px;
    letter-spacing: 0.14em;
    text-transform: uppercase;
    color: #111827;
    margin: 10px 0 18px;
  }

  .tc-card-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 16px;
    margin-bottom: 40px;
  }

  .tc-card {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.1);
    border-top-width: 3px;
    border-radius: 18px;
    padding: 24px;
    text-decoration: none;
    color: #0f172a;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
    transition: transform .18s ease, box-shadow .18s ease, border-color .18s ease;
  }

  .tc-card:hover {
    transform: translateY(-2px);
    border-color: rgba(29, 111, 216, 0.28);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
  }

  .tc-card.is-green { border-top-color: #22c55e; }
  .tc-card.is-blue { border-top-color: #3b82f6; }
  .tc-card.is-violet { border-top-color: #8b5cf6; }

  .tc-card-icon {
    width: 46px;
    height: 46px;
    border-radius: 14px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 16px;
  }

  .tc-card.is-green .tc-card-icon { background: #dcfce7; color: #16a34a; }
  .tc-card.is-blue .tc-card-icon { background: #dbeafe; color: #2563eb; }
  .tc-card.is-violet .tc-card-icon { background: #ede9fe; color: #7c3aed; }

  .tc-card h2 {
    margin: 0 0 8px;
    font-size: 18px;
    font-weight: 800;
    line-height: 1.25;
  }

  .tc-card p {
    margin: 0 0 16px;
    color: #4b5563;
    line-height: 1.55;
  }

  .tc-card-link {
    margin-top: 14px;
    display: inline-flex;
    align-items: center;
    gap: 8px;
    color: #0f172a;
    font-weight: 700;
    text-decoration: none;
  }

  .tc-card-link::after {
    content: "→";
    font-size: 16px;
  }

  .tc-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 3px 10px;
    border-radius: 999px;
    font-size: 12px;
    line-height: 1;
  }

  .tc-tag.is-live { color: #16a34a; background: #dcfce7; }
  .tc-tag.is-blue { color: #2563eb; background: #dbeafe; }
  .tc-tag.is-violet { color: #7c3aed; background: #ede9fe; }

  .tc-tag-dot {
    width: 8px;
    height: 8px;
    border-radius: 999px;
    background: currentColor;
  }

  .tc-comp {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 24px;
    padding: 26px 24px 24px;
    margin-bottom: 28px;
    box-shadow: 0 1px 3px rgba(15, 23, 42, 0.06);
  }

  .tc-comp-head {
    display: flex;
    justify-content: space-between;
    gap: 18px;
    align-items: end;
    margin-bottom: 22px;
  }

  .tc-comp-kicker {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    text-transform: uppercase;
    color: var(--tc-accent);
    margin-bottom: 8px;
  }

  .tc-comp-title {
    margin: 0;
    font-size: clamp(24px, 3vw, 34px);
    line-height: 1.04;
    font-weight: 800;
    color: #0f172a;
  }

  .tc-comp-copy {
    margin: 10px 0 0;
    color: #475569;
    line-height: 1.6;
    max-width: 640px;
  }

  .tc-comp-amount {
    min-width: 220px;
    text-align: right;
  }

  .tc-comp-amount-label {
    font-size: 12px;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #64748b;
    margin-bottom: 8px;
  }

  .tc-comp-amount-value {
    font-family: 'Syne', 'Segoe UI', sans-serif;
    font-size: clamp(34px, 5vw, 52px);
    line-height: 1;
    font-weight: 800;
    color: #0f172a;
  }

  .tc-comp-amount-sub {
    margin-top: 6px;
    color: #64748b;
    font-size: 13px;
  }

  .tc-comp-tier-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 12px;
    margin-bottom: 22px;
  }

  .tc-comp-tier {
    border: 1px solid #e2e8f0;
    border-radius: 18px;
    padding: 18px 18px 16px;
    background: #fff;
    transition: transform .2s ease, box-shadow .2s ease, border-color .2s ease, background .2s ease, color .2s ease;
  }

  .tc-comp-tier.is-active {
    transform: translateY(-2px);
    box-shadow: 0 12px 24px rgba(15, 23, 42, 0.08);
  }

  .tc-comp-tier.is-amber.is-active {
    background: #fffbeb;
    border-color: #fcd34d;
  }

  .tc-comp-tier.is-green.is-active {
    background: #f0fdf4;
    border-color: #86efac;
  }

  .tc-comp-tier.is-gray.is-active {
    background: #f8fafc;
    border-color: #cbd5e1;
  }

  .tc-comp-tier-range {
    font-size: 12px;
    color: #64748b;
    margin-bottom: 10px;
  }

  .tc-comp-tier-value {
    font-family: 'Syne', 'Segoe UI', sans-serif;
    font-size: 38px;
    line-height: 1;
    font-weight: 800;
    margin-bottom: 6px;
    color: #cbd5e1;
  }

  .tc-comp-tier.is-active.is-amber .tc-comp-tier-value { color: #d97706; }
  .tc-comp-tier.is-active.is-green .tc-comp-tier-value { color: #16a34a; }
  .tc-comp-tier.is-active.is-gray .tc-comp-tier-value { color: #64748b; }

  .tc-comp-tier-label {
    font-size: 13px;
    color: #475569;
  }

  .tc-comp-progress {
    height: 4px;
    background: #e2e8f0;
    border-radius: 999px;
    overflow: hidden;
    margin-bottom: 24px;
  }

  .tc-comp-progress-bar {
    height: 100%;
    width: 2%;
    background: #94a3b8;
    border-radius: 999px;
    transition: width .25s ease, background .25s ease;
  }

  .tc-comp-slider-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 24px;
  }

  .tc-comp-slider-label {
    display: flex;
    justify-content: space-between;
    gap: 10px;
    margin-bottom: 8px;
    color: #475569;
    font-size: 13px;
    font-weight: 700;
  }

  .tc-comp-slider-value {
    color: #0f172a;
  }

  .tc-comp-slider input[type="range"] {
    width: 100%;
    accent-color: #2563eb;
    cursor: pointer;
  }

  .tc-stat-grid {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 14px;
    margin-bottom: 28px;
  }

  .tc-stat {
    background: rgba(255,255,255,0.86);
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    padding: 18px 18px 16px;
  }

  .tc-stat-label {
    color: #6b7280;
    margin-bottom: 8px;
  }

  .tc-stat-value {
    font-size: 20px;
    line-height: 1.1;
    font-weight: 800;
    color: #111827;
  }

  .tc-stat-sub {
    color: #6b7280;
  }

  .tc-grid {
    display: grid;
    grid-template-columns: 1fr;
    gap: 16px;
    margin-bottom: 20px;
  }

  .tc-panel {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    padding: 24px;
  }

  .tc-panel h3 {
    margin: 0 0 18px;
    font-size: 18px;
    line-height: 1.2;
    font-weight: 800;
  }

  .tc-info-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 16px;
    margin-bottom: 20px;
  }

  .tc-accordion {
    display: grid;
    gap: 0;
  }

  .tc-accordion details {
    border-top: 1px solid rgba(15, 23, 42, 0.08);
    background: transparent;
    overflow: hidden;
  }

  .tc-accordion details:first-child {
    border-top: 0;
  }

  .tc-accordion summary {
    list-style: none;
    cursor: pointer;
    padding: 16px 0;
    font-weight: 700;
    color: #0f172a;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
  }

  .tc-accordion summary::-webkit-details-marker {
    display: none;
  }

  .tc-accordion summary::after {
    content: "+";
    color: #1d6fd8;
    font-size: 20px;
    line-height: 1;
    font-weight: 500;
  }

  .tc-accordion details[open] summary::after {
    content: "−";
  }

  .tc-accordion-body {
    padding: 0 0 18px;
    color: #4b5563;
    line-height: 1.65;
  }

  .tc-accordion-body p + p {
    margin-top: 10px;
  }

  .tc-about-list {
    display: grid;
    gap: 16px;
  }

  .tc-about-item {
    display: grid;
    grid-template-columns: 36px 1fr;
    gap: 14px;
    align-items: start;
    padding: 2px 0;
  }

  .tc-about-icon {
    width: 36px;
    height: 36px;
    border-radius: 12px;
    background: #eff6ff;
    color: #2563eb;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    font-size: 18px;
  }

  .tc-about-item h4 {
    margin: 0 0 4px;
    font-size: 15px;
    line-height: 1.25;
    font-weight: 800;
    color: #0f172a;
  }

  .tc-about-item p {
    margin: 0;
    color: #4b5563;
    line-height: 1.65;
  }

  .tc-about-tag {
    display: inline-flex;
    align-items: center;
    margin-top: 8px;
    padding: 4px 10px;
    border-radius: 999px;
    background: #dbeafe;
    color: #1e40af;
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.04em;
    text-transform: uppercase;
  }

  .tc-contact-panel {
    margin-bottom: 20px;
  }

  .tc-contact-copy {
    margin: 0 0 16px;
    color: #4b5563;
    line-height: 1.65;
    max-width: 760px;
  }

  .tc-contact-footer {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    flex-wrap: wrap;
    padding-top: 16px;
    border-top: 1px solid rgba(15, 23, 42, 0.08);
  }

  .tc-contact-email {
    display: inline-flex;
    align-items: center;
    gap: 10px;
    color: #0f172a;
    font-weight: 700;
    text-decoration: none;
  }

  .tc-contact-email:hover {
    color: #1d6fd8;
  }

  .tc-contact-email-note {
    color: #6b7280;
    line-height: 1.6;
  }

  .tc-actions {
    display: grid;
    gap: 12px;
  }

  .tc-step-list {
    display: grid;
    gap: 10px;
  }

  .tc-step {
    display: grid;
    grid-template-columns: 68px 1fr;
    gap: 12px;
    align-items: start;
    padding: 10px 0;
    border-bottom: 1px solid #edf2f7;
  }

  .tc-step:last-child {
    border-bottom: 0;
  }

  .tc-step-num {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 34px;
    padding: 0 10px;
    border-radius: 999px;
    background: #eff6ff;
    color: #1d4ed8;
    font-weight: 800;
    font-size: 13px;
  }

  .tc-action {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-height: 50px;
    border-radius: 14px;
    border: 1px solid rgba(15, 23, 42, 0.16);
    color: #0f172a;
    background: #fff;
    text-decoration: none;
    font-weight: 600;
  }

  .tc-action:hover {
    border-color: #9fb3d0;
    background: #f8fafc;
  }

  .tc-note-card {
    background: #fff;
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 18px;
    padding: 24px;
    margin-bottom: 16px;
  }

  .tc-note-card h3 {
    margin: 0 0 12px;
    font-size: 18px;
    font-weight: 800;
  }

  .tc-note-card p {
    margin: 0 0 16px;
    line-height: 1.62;
    color: #374151;
  }

  .tc-rights-card {
    border: 1px solid rgba(15, 23, 42, 0.08);
    border-radius: 16px;
    padding: 18px;
    background: #fbfdff;
  }

  .tc-rights-helper {
    margin: 0 0 14px;
    font-size: 13px;
    line-height: 1.6;
    color: #475569;
  }

  .tc-rights-grid {
    display: grid;
    grid-template-columns: repeat(2, 1fr);
    gap: 8px;
    margin-top: 14px;
  }

  .tc-rights-cell {
    background: #f1f5f9;
    border-radius: 10px;
    padding: 10px 12px;
  }

  .tc-rights-cell-label {
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0.07em;
    text-transform: uppercase;
    color: #94a3b8;
    margin-bottom: 4px;
  }

  .tc-rights-cell-value {
    font-size: 13px;
    font-weight: 700;
    color: #0f172a;
  }

  .tc-commission-note {
    margin: 16px 0 0;
    font-size: 13px;
    line-height: 1.6;
    color: #475569;
  }

  .tc-site-footer {
    margin-top: 44px;
    background: #0f1b2d;
    color: #dbe4f0;
    padding: 56px 0 24px;
  }

  .tc-site-footer-inner {
    max-width: 1120px;
    margin: 0 auto;
    padding: 0 16px;
  }

  .tc-site-footer-stats {
    display: grid;
    grid-template-columns: repeat(3, minmax(0, 1fr));
    gap: 18px;
    margin-bottom: 34px;
  }

  .tc-site-footer-stat {
    background: rgba(255, 255, 255, 0.06);
    border: 1px solid rgba(255, 255, 255, 0.08);
    border-radius: 20px;
    padding: 28px 24px 24px;
    text-align: center;
  }

  .tc-site-footer-stat-value {
    font-family: 'Syne', 'Segoe UI', sans-serif;
    font-size: clamp(42px, 5vw, 64px);
    line-height: 0.95;
    font-weight: 800;
    color: #ffffff;
    letter-spacing: -0.04em;
  }

  .tc-site-footer-stat-label {
    margin-top: 10px;
    font-size: 18px;
    color: rgba(226, 232, 240, 0.86);
  }

  .tc-site-footer-bottom {
    display: grid;
    grid-template-columns: 1fr auto 1fr;
    gap: 16px;
    align-items: center;
    padding-top: 22px;
    border-top: 1px solid rgba(255, 255, 255, 0.08);
    color: rgba(226, 232, 240, 0.72);
    font-size: 14px;
  }

  .tc-site-footer-bottom > :nth-child(2) {
    text-align: center;
  }

  .tc-site-footer-bottom > :last-child {
    text-align: right;
  }

  @media (max-width: 980px) {
    .tc-hero-grid,
    .tc-card-grid,
    .tc-stat-grid,
    .tc-grid,
    .tc-info-grid,
    .tc-contact-grid,
    .tc-rights-grid {
      grid-template-columns: 1fr;
    }

    .tc-hero {
      padding: 42px 22px 30px;
    }

    .tc-hero-visual {
      min-height: 250px;
    }

    .tc-comp-head,
    .tc-comp-slider-grid,
    .tc-comp-tier-grid {
      grid-template-columns: 1fr;
      display: grid;
    }

    .tc-comp-head {
      align-items: start;
    }

    .tc-comp-amount {
      text-align: left;
      min-width: 0;
    }

    .tc-site-footer-stats,
    .tc-site-footer-bottom {
      grid-template-columns: 1fr;
    }

    .tc-site-footer-bottom > :nth-child(2),
    .tc-site-footer-bottom > :last-child {
      text-align: left;
    }
  }

  @media (max-width: 640px) {
    .tc-page {
      padding-left: 12px;
      padding-right: 12px;
    }

    .tc-page.tc-page--fullbleed {
      padding-left: 0;
      padding-right: 0;
    }

    .tc-page.tc-page--fullbleed > :not(.tc-hero) {
      padding-left: 12px;
      padding-right: 12px;
    }

    .tc-page.tc-page--fullbleed > .tc-hero {
      padding-left: 12px;
      padding-right: 12px;
      padding-top: 4px;
      padding-bottom: 72px;
    }

    .tc-page.tc-page--fullbleed > .tc-hero::after {
      height: 110px;
      filter: blur(8px);
    }

    .tc-hero h1 {
      font-size: 52px;
    }

    .tc-summary {
      grid-template-columns: 1fr;
      gap: 4px;
    }
  }

  /* ── Cinematiske gradienter over 3D-canvas ── */
  .tc-hero-3d {
    position: relative;
  }
  .tc-grad {
    position: absolute;
    pointer-events: none;
    z-index: 1;
  }
  .tc-grad-left {
    inset: 0;
    background: linear-gradient(
      108deg,
      #0f1b2d 0%,
      rgba(15,27,45,0.92) 22%,
      rgba(15,27,45,0.60) 48%,
      rgba(15,27,45,0.15) 72%,
      transparent 100%
    );
  }
  .tc-grad-bottom {
    bottom: 0; left: 0; right: 0;
    height: 44%;
    background: linear-gradient(to top, #0f1b2d 0%, rgba(15,27,45,0.7) 42%, transparent 100%);
  }
  .tc-grad-top {
    top: 0; left: 0; right: 0;
    height: 14%;
    background: linear-gradient(to bottom, rgba(15,27,45,0.85) 0%, transparent 100%);
  }
  .tc-grad-right {
    top: 0; right: 0; bottom: 0;
    width: 10%;
    background: linear-gradient(to left, #0f1b2d 0%, transparent 100%);
  }
  .tc-grad-fade {
    inset: 0;
    background: #0f1b2d;
    transition: opacity 1.2s ease;
  }

  @keyframes tc-train-cross {
    0% {
      transform: translateX(-230px);
    }
    100% {
      transform: translateX(305px);
    }
  }

  @keyframes tc-smoke-rise-1 {
    0% {
      transform: translate(0, 0) scale(0.82);
      opacity: 0;
    }
    18% {
      opacity: .44;
    }
    100% {
      transform: translate(-10px, -22px) scale(1.22);
      opacity: 0;
    }
  }

  @keyframes tc-smoke-rise-2 {
    0% {
      transform: translate(0, 0) scale(0.78);
      opacity: 0;
    }
    20% {
      opacity: .30;
    }
    100% {
      transform: translate(-8px, -28px) scale(1.3);
      opacity: 0;
    }
  }

  @keyframes tc-smoke-rise-3 {
    0% {
      transform: translate(0, 0) scale(0.74);
      opacity: 0;
    }
    22% {
      opacity: .16;
    }
    100% {
      transform: translate(-6px, -34px) scale(1.36);
      opacity: 0;
    }
  }

  @keyframes tc-light-pulse {
    0%, 100% {
      opacity: .82;
      transform: scale(0.96);
    }
    50% {
      opacity: 1;
      transform: scale(1.04);
    }
  }

  @keyframes tc-headlight-pulse {
    0%, 100% {
      opacity: .86;
      transform: scale(1);
    }
    50% {
      opacity: 1;
      transform: scale(1.18);
    }
  }

  .tc-ferry .tc-about-icon {
    background: #ccfbf1;
    color: #0f766e;
  }
</style>

<?= $this->element('rail_entry_nav', [
    'railNav' => $railNav,
    'brandHref' => $brandHref,
    'brandLabel' => 'TrainClaim',
    'landingAnchors' => true,
    'fullBleedNav' => $fullBleedLanding,
    'navTheme' => $fullBleedLanding ? 'dark' : 'light',
    'languageLinks' => $languageLinks ?? [],
]) ?>

<div class="tc-page tc-ferry<?= $fullBleedLanding ? ' tc-page--fullbleed' : '' ?>">
  <section class="tc-hero">
    <div class="tc-hero-grid">
      <!-- Tekst venstre -->
      <div>
        <div class="tc-pill">Færge entry · spring trin 1 over</div>
        <h1>Afklar hurtigt,<span>om din overfart giver ret til kompensation.</span></h1>
        <p class="tc-hero-copy">Vi går direkte til billet, afgang og hændelse. Derefter vurderer vi kompensation, assistance og refusion eller ombooking i færgesporet.</p>
        <div class="tc-hero-actions">
          <a class="tc-btn tc-btn-live" href="<?= h($quickLinks['ongoing']) ?>">
            <span class="tc-live-dot" aria-hidden="true"></span>
            Igangværende rejse
          </a>
          <a class="tc-btn tc-btn-secondary" href="<?= h($quickLinks['completed']) ?>">Afsluttet rejse</a>
          <a class="tc-btn tc-btn-secondary" href="<?= h($quickLinks['beforeStart']) ?>">Rejsen starter senere</a>
        </div>
        <?php if (!$fullBleedLanding): ?>
        <div class="tc-preview-links">
          <span class="tc-preview-label">Preview flows</span>
          <a class="tc-preview-link" href="<?= h($tc6PreviewLinks['entitlements']) ?>">Trin 1: Start</a>
          <a class="tc-preview-link" href="<?= h($tc6PreviewLinks['departure']) ?>">Trin 2: Vælg afgang</a>
          <a class="tc-preview-link" href="<?= h($tc6PreviewLinks['incident']) ?>">Trin 3: Hændelse</a>
          <a class="tc-preview-link" href="<?= h($tc6PreviewLinks['remedies']) ?>">Trin 4: Refusion / ombooking</a>
          <a class="tc-preview-link" href="<?= h($tc6PreviewLinks['assistance']) ?>">Trin 5: Assistance</a>
          <a class="tc-preview-link" href="<?= h($tc6PreviewLinks['contact']) ?>">Trin 6: Kontakttrin</a>
        </div>
        <?php endif; ?>
      </div>

      <!-- Hero visual højre -->
      <div class="tc-hero-3d" aria-hidden="true">
        <svg class="tc-hero-svg" viewBox="0 0 520 360" role="presentation" focusable="false">
          <defs>
            <linearGradient id="ferrySky" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stop-color="#113447" />
              <stop offset="58%" stop-color="#10212c" />
              <stop offset="100%" stop-color="#0b1620" />
            </linearGradient>
            <linearGradient id="ferrySea" x1="0" x2="0" y1="0" y2="1">
              <stop offset="0%" stop-color="#155e75" />
              <stop offset="100%" stop-color="#0f172a" />
            </linearGradient>
          </defs>
          <rect x="0" y="0" width="520" height="360" rx="20" fill="url(#ferrySky)" />
          <rect x="0" y="240" width="520" height="120" fill="url(#ferrySea)" />
          <path d="M0 256c44-6 88-6 132 0s88 6 132 0 88-6 132 0 88 6 124 0v104H0z" fill="#0f172a" opacity=".78" />
          <path class="tc-anim-smoke-1" d="M0 274c40-8 80-8 120 0s80 8 120 0 80-8 120 0 80 8 160 0" stroke="#67e8f9" stroke-width="4" opacity=".22" fill="none" />
          <path class="tc-anim-smoke-2" d="M0 292c40-8 80-8 120 0s80 8 120 0 80-8 120 0 80 8 160 0" stroke="#ccfbf1" stroke-width="3" opacity=".16" fill="none" />
          <g class="tc-anim-train">
            <path d="M120 190h188c20 0 36 12 44 28l18 36H154c-24 0-34-6-42-20l-16-22c-8-12 0-22 24-22z" fill="#e2e8f0" />
            <rect x="172" y="160" width="132" height="44" rx="12" fill="#f8fafc" />
            <rect x="192" y="174" width="92" height="12" rx="6" fill="#14b8a6" opacity=".86" />
            <rect x="312" y="184" width="54" height="18" rx="9" fill="#0f172a" />
            <circle cx="368" cy="229" r="7" fill="#fef3c7" opacity=".92" />
            <circle class="tc-anim-headlight" cx="372" cy="229" r="16" fill="#fef3c7" opacity=".18" />
          </g>
          <rect x="404" y="104" width="10" height="140" rx="5" fill="#475569" />
          <circle cx="409" cy="96" r="20" fill="#fef3c7" opacity=".92" />
          <circle cx="409" cy="96" r="32" fill="#fef3c7" opacity=".16" />
        </svg>
      </div>
    </div>
  </section>

  <div class="tc-arrow-wrap">
    <span class="tc-arrow" aria-hidden="true">↓</span>
  </div>

  <p class="tc-label">Vælg rejsetype og gå direkte videre</p>
  <section class="tc-card-grid">
    <?php foreach ($travelCards as $card): ?>
      <a class="tc-card <?= h((string)$card['accentClass']) ?>" href="<?= h((string)$card['href']) ?>">
        <span class="tc-card-icon" aria-hidden="true">
          <?php if ($card['icon'] === 'live'): ?>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M3 18h18"></path>
              <path d="M5 16l2-7h10l2 7"></path>
              <path d="M9 9V6h6v3"></path>
            </svg>
          <?php elseif ($card['icon'] === 'file'): ?>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
              <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
              <polyline points="14 2 14 8 20 8"></polyline>
              <line x1="16" y1="13" x2="8" y2="13"></line>
              <line x1="16" y1="17" x2="8" y2="17"></line>
            </svg>
          <?php else: ?>
            <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.1" stroke-linecap="round" stroke-linejoin="round">
              <circle cx="12" cy="12" r="9"></circle>
              <path d="M12 7v6l4 2"></path>
            </svg>
          <?php endif; ?>
        </span>
        <h2><?= h((string)$card['title']) ?></h2>
        <p><?= h((string)$card['summary']) ?></p>
        <span class="tc-tag <?= h((string)$card['badgeClass']) ?>">
          <span class="tc-tag-dot" aria-hidden="true"></span>
          <?= h((string)$card['badge']) ?>
        </span>
        <span class="tc-card-link">Start her</span>
      </a>
    <?php endforeach; ?>
  </section>

  <section class="tc-comp" id="ferry-comp-teaser">
    <div class="tc-comp-head">
      <div>
        <div class="tc-comp-kicker">Hvad kan du få?</div>
        <h2 class="tc-comp-title">Prøv et hurtigt færge-estimat</h2>
        <p class="tc-comp-copy">Juster forsinkelse og billetpris og se, hvordan kompensationen typisk bevæger sig mellem 0%, 25% og 50% i færgesporet.</p>
      </div>
      <div class="tc-comp-amount">
        <div class="tc-comp-amount-label">Estimeret kompensation</div>
        <div class="tc-comp-amount-value" id="tcCompAmount">75 kr.</div>
        <div class="tc-comp-amount-sub" id="tcCompAmountSub">25% af 300 kr. ved 75 minutters forsinkelse</div>
      </div>
    </div>

    <div class="tc-comp-tier-grid">
      <div class="tc-comp-tier tc-comp-tier-card is-gray" data-tier="0">
        <div class="tc-comp-tier-range">0–59 min</div>
        <div class="tc-comp-tier-value">0%</div>
        <div class="tc-comp-tier-label">Ingen kompensation</div>
      </div>
      <div class="tc-comp-tier tc-comp-tier-card is-amber is-active" data-tier="25">
        <div class="tc-comp-tier-range">60–119 min</div>
        <div class="tc-comp-tier-value">25%</div>
        <div class="tc-comp-tier-label">25% af billetprisen</div>
      </div>
      <div class="tc-comp-tier tc-comp-tier-card is-green" data-tier="50">
        <div class="tc-comp-tier-range">120+ min</div>
        <div class="tc-comp-tier-value">50%</div>
        <div class="tc-comp-tier-label">50% af billetprisen</div>
      </div>
    </div>

    <div class="tc-comp-progress">
      <div class="tc-comp-progress-bar" id="tcCompProgress"></div>
    </div>

    <div class="tc-comp-slider-grid">
      <div class="tc-comp-slider">
        <div class="tc-comp-slider-label">
          <span>Din forsinkelse</span>
          <span class="tc-comp-slider-value" id="tcDelayValue">75 min</span>
        </div>
        <input id="tcDelayRange" type="range" min="0" max="180" value="75" />
      </div>
      <div class="tc-comp-slider">
        <div class="tc-comp-slider-label">
          <span>Billetpris</span>
          <span class="tc-comp-slider-value" id="tcPriceValue">300 kr.</span>
        </div>
        <input id="tcPriceRange" type="range" min="50" max="2000" step="10" value="300" />
      </div>
    </div>
  </section>

  <section class="tc-stat-grid">
    <div class="tc-stat">
      <div class="tc-stat-label">Tid til vurdering</div>
      <div class="tc-stat-value">Ca. 3 min</div>
      <div class="tc-stat-sub">Til du når hændelsen</div>
    </div>
      <div class="tc-stat">
        <div class="tc-stat-label">Vi vurderer</div>
        <div class="tc-stat-value">3 spor</div>
        <div class="tc-stat-sub">Kompensation, assistance og refusion / ombooking</div>
    </div>
    <div class="tc-stat">
      <div class="tc-stat-label">Kommission</div>
      <div class="tc-stat-value">20%</div>
      <div class="tc-stat-sub">Kun hvis vi gennemfører kravet for dig</div>
    </div>
  </section>

  <section class="tc-grid">
    <div class="tc-panel">
      <h3>Det vurderer vi hurtigt</h3>
      <div class="tc-rights-card">
        <p class="tc-rights-helper">Vi vurderer hurtigt, om du kan få kompensation og dækning for nødvendige udgifter under rejsen.</p>
        <div class="tc-rights-grid">
          <div class="tc-rights-cell">
            <div class="tc-rights-cell-label">Kompensation</div>
            <div class="tc-rights-cell-value">25% eller 50% af billetpris</div>
          </div>
          <div class="tc-rights-cell">
            <div class="tc-rights-cell-label">Assistance</div>
            <div class="tc-rights-cell-value">Mad, hotel og nødvendige udgifter under overfarten</div>
          </div>
          <div class="tc-rights-cell">
            <div class="tc-rights-cell-label">Refusion / ombooking</div>
            <div class="tc-rights-cell-value">Ny overfart, pengene tilbage og nødvendige ombookingsudgifter</div>
          </div>
          <div class="tc-rights-cell">
            <div class="tc-rights-cell-label">Vores rolle</div>
            <div class="tc-rights-cell-value">Vi afklarer først dit færgespor og kan derefter føre kravet videre for dig</div>
          </div>
        </div>
        <p class="tc-commission-note">Vi tager 20% i kommission, hvis vi gennemfører kravet for dig.</p>
      </div>
    </div>
  </section>

  <section class="tc-info-grid">
    <section class="tc-panel" id="faq">
      <h3>FAQ</h3>
      <div class="tc-accordion">
        <details open>
          <summary>Hvor hurtigt får jeg en vurdering?</summary>
          <div class="tc-accordion-body">
            <p>Du når normalt til hændelsen på cirka 3 minutter. Derfra kan vi hurtigt vurdere, om kompensation, assistance eller refusion / ombooking er relevant.</p>
          </div>
        </details>
        <details>
          <summary>Hvad vurderer I i færgesporet?</summary>
          <div class="tc-accordion-body">
            <p>Vi vurderer tre spor: kompensation, assistance og refusion / ombooking. Det betyder både selve billetkravet og nødvendige udgifter under rejsen.</p>
          </div>
        </details>
        <details>
          <summary>Hvornår tager I 20% i kommission?</summary>
          <div class="tc-accordion-body">
            <p>Vi tager kun 20% i kommission, hvis vi gennemfører kravet for dig. Du betaler ikke kommission bare for at få den første vurdering i flowet.</p>
          </div>
        </details>
        <details>
          <summary>Dækker I hotel, mad og transport videre?</summary>
          <div class="tc-accordion-body">
            <p>Ja, når færgereglerne åbner for assistance eller ombooking, vurderer vi også nødvendige udgifter som mad, hotel og videre transport.</p>
          </div>
        </details>
      </div>
    </section>

    <section class="tc-panel" id="om-os">
      <h3>Om os</h3>
      <div class="tc-about-list">
        <article class="tc-about-item">
          <span class="tc-about-icon" aria-hidden="true">⛴️</span>
          <div>
            <h4>Vi vurderer færgepassagerers rettigheder</h4>
            <p>TrainClaim hjælper med at afklare, om du kan få kompensation og dækning for nødvendige udgifter ved assistance, refusion eller ombooking.</p>
            <span class="tc-about-tag">EU 1177/2010</span>
          </div>
        </article>
        <article class="tc-about-item">
          <span class="tc-about-icon" aria-hidden="true">🧭</span>
          <div>
            <h4>Vi går hurtigt til det afgørende</h4>
            <p>Landing-siden flytter rejsestatus op foran flowet. Derfor går du direkte videre til billet, afgang og hændelse, som afgør retningen i sagen.</p>
          </div>
        </article>
        <article class="tc-about-item">
          <span class="tc-about-icon" aria-hidden="true">🤝</span>
          <div>
            <h4>20% kun hvis vi gennemfører kravet</h4>
            <p>Du betaler ikke kommission for den første vurdering. Vi tager kun 20%, hvis vi gennemfører kravet for dig.</p>
          </div>
        </article>
      </div>
    </section>
  </section>

  <section class="tc-panel tc-contact-panel" id="kontakt">
    <h3>Kontakt</h3>
    <p class="tc-contact-copy">Har du spørgsmål før du går i gang, kan du skrive til os. Vi holder kontakten enkel på landing-siden, så fokus stadig er på at komme hurtigt frem til vurderingen.</p>
    <div class="tc-contact-footer">
      <a class="tc-contact-email" href="mailto:kontakt@trainclaim.dk">
        <span aria-hidden="true">✉️</span>
        <span>kontakt@trainclaim.dk</span>
      </a>
      <span class="tc-contact-email-note">Vi svarer så hurtigt som muligt og hjælper dig videre til det rigtige færgespor.</span>
    </div>
  </section>

</div>

<?php if ($fullBleedLanding): ?>
  <footer class="tc-site-footer">
    <div class="tc-site-footer-inner">
      <div class="tc-site-footer-stats">
        <div class="tc-site-footer-stat">
          <div class="tc-site-footer-stat-value">18.200+</div>
          <div class="tc-site-footer-stat-label">færgesager vurderet</div>
        </div>
        <div class="tc-site-footer-stat">
          <div class="tc-site-footer-stat-value">Art. 18-20</div>
          <div class="tc-site-footer-stat-label">refusion, ombooking og assistance</div>
        </div>
        <div class="tc-site-footer-stat">
          <div class="tc-site-footer-stat-value">3 min</div>
          <div class="tc-site-footer-stat-label">til hurtig vurdering</div>
        </div>
      </div>
      <div class="tc-site-footer-bottom">
        <div><strong>FerryClaim</strong></div>
        <div>Baseret paa passagerrettigheder til soes</div>
        <div>&copy; 2026</div>
      </div>
    </div>
  </footer>
<?php endif; ?>

<script>
  (function () {
    const uiLang = <?= json_encode($lang, JSON_UNESCAPED_SLASHES) ?>;
    const delayInput = document.getElementById('tcDelayRange');
    const priceInput = document.getElementById('tcPriceRange');
    const delayValue = document.getElementById('tcDelayValue');
    const priceValue = document.getElementById('tcPriceValue');
    const amountValue = document.getElementById('tcCompAmount');
    const amountSub = document.getElementById('tcCompAmountSub');
    const progress = document.getElementById('tcCompProgress');
    const tiers = Array.from(document.querySelectorAll('.tc-comp-tier-card'));

    if (!delayInput || !priceInput || !delayValue || !priceValue || !amountValue || !amountSub || !progress || !tiers.length) {
      return;
    }

    function formatDkk(value) {
      const locale = uiLang === 'fr' ? 'fr-FR' : 'da-DK';
      return new Intl.NumberFormat(locale, { maximumFractionDigits: 0 }).format(value) + ' kr.';
    }

    function updateCompTeaser() {
      const delay = Number(delayInput.value || 0);
      const price = Number(priceInput.value || 0);

      let pct = 0;
      let tierKey = '0';
      let color = '#94a3b8';
      let progressWidth = '2%';

      if (delay >= 120) {
        pct = 50;
        tierKey = '50';
        color = '#16a34a';
        progressWidth = '100%';
      } else if (delay >= 60) {
        pct = 25;
        tierKey = '25';
        color = '#d97706';
        progressWidth = `${33 + ((delay - 60) / 60) * 33}%`;
      } else if (delay > 0) {
        progressWidth = `${Math.max(2, (delay / 60) * 33)}%`;
      }

      const amount = Math.round(price * pct / 100);

      delayValue.textContent = delay + ' min';
      priceValue.textContent = formatDkk(price);
      amountValue.textContent = pct > 0 ? formatDkk(amount) : '0 kr.';
      amountSub.textContent = pct > 0
        ? (uiLang === 'fr'
            ? `${pct} % de ${formatDkk(price)} pour ${delay} minutes de retard`
            : `${pct}% af ${formatDkk(price)} ved ${delay} minutters forsinkelse`)
        : (uiLang === 'fr'
            ? 'Aucune compensation en dessous de 60 minutes de retard'
            : 'Ingen kompensation under 60 minutters forsinkelse');

      progress.style.width = progressWidth;
      progress.style.background = color;

      tiers.forEach(function (tier) {
        tier.classList.toggle('is-active', tier.getAttribute('data-tier') === tierKey);
      });
    }

    delayInput.addEventListener('input', updateCompTeaser);
    priceInput.addEventListener('input', updateCompTeaser);
    updateCompTeaser();
  })();
</script>
