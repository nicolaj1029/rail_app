<?php
/** @var \App\View\AppView $this */
?>
<h1>Start – Upload og valg</h1>
<form method="post">
  <div>
    <label for="journey_json">Rejsedata (JSON)</label>
    <textarea name="journey_json" id="journey_json" rows="8" style="width:100%">{"country":{"value":"EU"},"ticketPrice":{"value":"100 EUR"}}</textarea>
  </div>
  <div>
    <label for="ocr_text">Billet-tekst (OCR)</label>
    <textarea name="ocr_text" id="ocr_text" rows="5" style="width:100%"></textarea>
  </div>
  <fieldset style="background:#ffa50022; padding:10px;">
    <legend>TRIN 1 – Sæt X</legend>
    <label><input type="radio" name="travel_state" value="completed" /> Rejsen er afsluttet</label><br/>
    <label><input type="radio" name="travel_state" value="ongoing" /> Rejsen er påbegyndt – jeg er i toget eller er ved/skal til at skifte forbindelse</label><br/>
    <label><input type="radio" name="travel_state" value="before_start" /> Jeg står på banegården og skal til at påbegynde rejsen</label>
  </fieldset>
  <div>
    <label><input type="checkbox" name="eu_only" checked /> EU-only forsinkelse</label>
  </div>
  <div>
    <label><input type="checkbox" name="art9_opt_in" /> Inkludér Art. 9 (kun på anmodning)</label>
  </div>
  <button type="submit">Fortsæt</button>
</form>
