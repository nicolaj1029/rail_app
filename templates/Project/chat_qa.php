<?php
/** @var \App\View\AppView $this */
/** @var array<string,string> $links */
?>
<style>
  .chatqa-page { max-width: 980px; margin: 0 auto; padding: 16px; font-family: system-ui, -apple-system, Segoe UI, sans-serif; }
  .chatqa-page h1, .chatqa-page h2 { color: #111; margin-bottom: 6px; }
  .chatqa-page p { color: #333; line-height: 1.45; }
  .chatqa-page ul, .chatqa-page ol { margin: 6px 0 12px 18px; }
  .chatqa-page li { margin: 4px 0; }
  .chatqa-cards { display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 12px; }
  .chatqa-card { border: 1px solid #e5e7eb; border-radius: 10px; padding: 12px; background: #fff; box-shadow: 0 1px 2px rgba(0,0,0,.04); }
  .chatqa-card h3 { margin: 0 0 8px; font-size: 16px; color: #111; }
  .chatqa-card a { color: #0a6fd8; text-decoration: none; }
  .chatqa-card a:hover { text-decoration: underline; }
  .chatqa-note { color: #555; font-size: 13px; }
  .chatqa-callout { border-left: 4px solid #0a6fd8; background: #f7fbff; padding: 12px; border-radius: 8px; margin: 12px 0; }
  .chatqa-code { background: #f6f8fa; border-radius: 8px; padding: 10px; overflow: auto; font-size: 12px; }
  code { background: #f4f4f5; padding: 1px 4px; border-radius: 4px; }
</style>

<div class="chatqa-page">
  <h1>Chat QA / RAG / Groq</h1>
  <p>Denne side samler den nuvaerende status for chatbot-fundamentet i appen: regulation-RAG, Groq audits og den anbefalede vej til en chat oven paa det eksisterende flow. Siden er en arbejdsindgang, ikke en offentlig chat-UI.</p>

  <div class="chatqa-callout">
    <strong>Anbefalet arkitektur i denne kodebase:</strong>
    <div>Chatten skal styre dialogen, men dine eksisterende services og evaluators skal fortsat vaere sandhedskilden. Det vil sige: chatten spoerger, pipeline/evaluators beregner, og regulation-index giver citater/forklaring.</div>
  </div>

  <h2>Hvad der findes nu</h2>
  <div class="chatqa-cards">
    <div class="chatqa-card">
      <h3>Admin chat panel</h3>
      <p class="chatqa-note">Der er nu et faktisk admin-chat panel med session-state, whitelisted spoergsmaal, citations og links tilbage til flowet.</p>
      <div><a href="<?= h($links['adminChat'] ?? '/admin/chat') ?>" target="_blank">Aabn /admin/chat</a></div>
    </div>
    <div class="chatqa-card">
      <h3>Regulation RAG</h3>
      <p class="chatqa-note">Chunked index af forordningen findes allerede lokalt og kan bruges til search/quote med citations.</p>
      <div><a href="<?= h($links['regulationSearch']) ?>" target="_blank">API: regulation/search</a></div>
      <div><a href="<?= h($links['regulationQuote']) ?>" target="_blank">API: regulation/quote</a></div>
    </div>
    <div class="chatqa-card">
      <h3>Audit med Groq</h3>
      <p class="chatqa-note">CLI-kommandoer og admin audit-view findes allerede. Det er den hurtigste vej til compliance review mod forordningen.</p>
      <div><a href="<?= h($links['audit']) ?>" target="_blank">Admin audit</a></div>
    </div>
    <div class="chatqa-card">
      <h3>Beregningsmotor</h3>
      <p class="chatqa-note">Din pipeline kan allerede tage wizard-data og returnere profile, claim, compensation, refund, refusion og logs.</p>
      <div><a href="<?= h($links['pipeline']) ?>" target="_blank">API: pipeline/run</a></div>
      <div><a href="<?= h($links['scenarioEval']) ?>" target="_blank">Fixtures med eval</a></div>
    </div>
  </div>

  <h2>Hvordan chatten boer bruge flowet</h2>
  <ol>
    <li>Chatten holder en tynd conversation-state oven paa <code>flow.form</code>, <code>flow.meta</code>, <code>flow.compute</code> og <code>flow.flags</code>.</li>
    <li>Naeste spoergsmaal bestemmes af samme gating-logik som stepperen og TRIN 5-outputtet.</li>
    <li>Efter hvert svar kalder chatten pipeline-preview og viser kun spoergsmaal der aendrer udfaldet.</li>
    <li>Forklaringer og citater kommer fra regulation-index, ikke fra fri LLM-hukommelse.</li>
    <li>TRIN 10 i chat skal spejle din nuvaerende split mellem B = claim-assist/data-pack og C = instant payout senere.</li>
  </ol>

  <h2>Foerste MVP jeg vil anbefale</h2>
  <div class="chatqa-cards">
    <div class="chatqa-card">
      <h3>Admin chat foerst</h3>
      <p>Lav chatten som en admin-/QA-feature foerst. Saa kan du teste state, citations og preview uden at laegge det ud i passagerflowet.</p>
      <p class="chatqa-note">Det passer godt til den nuvaerende kodebase, fordi du allerede har audit, fixtures og regulation APIs.</p>
    </div>
    <div class="chatqa-card">
      <h3>Tool-calls i backend</h3>
      <p>Backend boer have et lille <code>/api/chat</code>-endpoint eller en service med disse operationer:</p>
      <ul>
        <li><code>get_flow_state()</code></li>
        <li><code>set_flow_value(key, value)</code></li>
        <li><code>run_preview()</code></li>
        <li><code>search_regulation(query)</code></li>
        <li><code>quote_regulation(id)</code></li>
      </ul>
    </div>
    <div class="chatqa-card">
      <h3>Groq-rolle</h3>
      <p>Groq skal bruges til dialog, forklaring og strukturering. Groq skal ikke afgore kompensationsretten alene.</p>
      <p class="chatqa-note">Whitelist kun hvilke felter modellen maa saette. Hold PII ude af prompts saa langt det er muligt.</p>
    </div>
  </div>

  <h2>Forslag til konkret arbejdsraekkefoelge</h2>
  <ol>
    <li>Behold <a href="<?= h($links['flowQa']) ?>" target="_blank">Flow QA</a> som sandhedsreference for det visuelle flow.</li>
    <li>Brug regulation search/quote til citations i forklaringslaget.</li>
    <li>Byg en intern admin-chat side foerst.</li>
    <li>Kobl den paa <code>/api/pipeline/run</code> for preview efter hvert svar.</li>
    <li>Indfoer season-pass som chat-mode oven paa din nuvaerende TRIN 10 B/C-model.</li>
  </ol>

  <h2>CLI / hurtig test</h2>
  <div class="chatqa-code"><pre><?= h("python scripts\\regulations\\index_32021r0782_da.py\nphp bin/cake.php regulation_audit --limit 6\nphp bin/cake.php ai_audit\n") ?></pre></div>

  <h2>Afgrænsning</h2>
  <ul>
    <li>Chatten findes nu som intern admin-side paa <code>/admin/chat</code>.</li>
    <li>Det er stadig ikke en passager-chat; den er beregnet til admin/QA og flow-verifikation.</li>
    <li>Naeste udbygning kan vaere pipeline-preview efter hvert svar og derefter evt. Groq-stoettet forklaringslag.</li>
  </ul>
</div>
