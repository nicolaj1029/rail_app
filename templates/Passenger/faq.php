<?php
/** @var \App\View\AppView $this */
?>
<?= $this->element('passenger_sidebar', compact('passengerNav')) ?>

<style>
  .faq-page { max-width: 1080px; margin: 0 auto; padding: 12px 0 24px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .faq-hero { border: 1px solid #dce7ef; border-radius: 24px; background: linear-gradient(135deg, #ffffff 0%, #f5fbff 100%); padding: 24px; margin-bottom: 18px; }
  .faq-hero h1 { margin: 0 0 10px; font-size: 38px; }
  .faq-grid { display:grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; }
  .faq-card { border: 1px solid #e5edf2; border-radius: 18px; background: #fff; padding: 18px; box-shadow: 0 8px 20px rgba(15, 23, 42, .04); }
  .faq-card h2 { margin-top: 0; font-size: 20px; }
  .faq-card p { color: #475569; line-height: 1.55; margin-bottom: 0; }
</style>

<div class="faq-page">
  <section class="faq-hero">
    <div style="font-size:13px; font-weight:700; letter-spacing:.04em; color:#0b86b5; text-transform:uppercase; margin-bottom:10px;">Passagerhjaelp</div>
    <h1>FAQ</h1>
    <p>Her samles de korte forklaringer til det nye kontrolpanel. Frontflowet for air completed er gjort kort; resten af informationen kan flyttes til sagsbackend bagefter.</p>
  </section>

  <div class="faq-grid">
    <section class="faq-card">
      <h2>Hvorfor er air-flowet kortere?</h2>
      <p>Air completed er bevidst skåret ned til de vigtigste haendelses- og kompensationsfakta. Booking, dokumenter og ekstraudgifter kan bagefter udfyldes i sagsbackend.</p>
    </section>

    <section class="faq-card">
      <h2>Hvor uploader jeg billet og kvitteringer?</h2>
      <p>Brug <strong>Sager</strong>-siden. Her markerer du, om der er dokumenter og udgifter, og derefter kan du fortsaette til resultat, ansøgeroplysninger og samtykke.</p>
    </section>

    <section class="faq-card">
      <h2>Hvorfor spørger incident om udgifter?</h2>
      <p>Det felt bruges kun som trigger. Hvis passageren markerer udgifter i incident, bliver ekstraudgiftssektionen aktiv i sagsbackend i stedet for i det korte frontflow.</p>
    </section>

    <section class="faq-card">
      <h2>Er det gamle flow fjernet?</h2>
      <p>Nej. Det gamle wizard-flow findes stadig via <code>/flow/start</code>. Det nye kontrolpanel er et ekstra lag oven på den samme motor og de samme beregninger.</p>
    </section>
  </div>
</div>
</div>
</div>
