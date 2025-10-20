<?php
/** @var \App\View\AppView $this */
/** @var array<string,string> $countries */
/** @var array<string,array<string,string>> $operators */
/** @var array<string,string[]> $products */
/** @var array<string,array<string,array{notes?:string,source?:string,exemptions?:array,scope?:?string}>> $overrideMeta */
?>
<style>
  .grid { display:grid; grid-template-columns: repeat(auto-fit,minmax(260px,1fr)); gap:12px; }
  .muted { color:#666; font-size: 13px; }
  ul.inline { list-style: disc; padding-left: 18px; }
</style>
<div class="content">
  <h1>Start kravberegning</h1>
  <p>Vælg land → operatør → produkt. Listen opdateres automatisk efter dit valg. Udfyld derefter basisfelter og tryk Beregn.</p>

  <?= $this->Form->create(null, ['url' => ['action' => 'compute']]) ?>
  <div class="grid">
    <?= $this->Form->control('delay_min', ['label' => 'Forsinkelse (minutter)', 'type' => 'number', 'min' => 0]) ?>
    <?= $this->Form->control('refund_already', ['label' => 'Refusion allerede udbetalt', 'type' => 'checkbox']) ?>
    <?= $this->Form->control('known_delay_before_purchase', ['label' => 'Forsinkelsen var kendt før køb', 'type' => 'checkbox']) ?>
    <?= $this->Form->control('extraordinary', ['label' => 'Ekstraordinære forhold', 'type' => 'checkbox']) ?>
    <?= $this->Form->control('self_inflicted', ['label' => 'Selvforskyldt', 'type' => 'checkbox']) ?>
    <?= $this->Form->hidden('scope', ['id' => 'scopeField', 'value' => '']) ?>
  </div>

  <?php
    // Build select option lists
    $countryOptions = $countries;
    $operatorOptionsByCountry = $operators;
    $productOptionsByOperator = $products;
  ?>

  <div class="grid">
    <?= $this->Form->control('country', [
      'label' => 'Land',
      'type' => 'select',
      'options' => $countryOptions,
      'empty' => 'Vælg land',
      'id' => 'countrySelect'
    ]) ?>

    <?= $this->Form->control('operator', [
      'label' => 'Operatør',
      'type' => 'select',
      'options' => [],
      'empty' => 'Vælg operatør',
      'id' => 'operatorSelect'
    ]) ?>

    <?= $this->Form->control('product', [
      'label' => 'Produkt',
      'type' => 'select',
      'options' => [],
      'empty' => 'Vælg produkt',
      'id' => 'productSelect'
    ]) ?>
  </div>

  <div class="muted" style="margin-top:8px;">Produkter for valgt operatør:</div>
  <ul class="inline" id="productList"></ul>
  <div id="productHint" class="muted" style="margin-top:6px; display:none;"></div>

  <?= $this->Form->button('Beregn') ?>
  <?= $this->Form->end() ?>

  <p class="muted">Dokumenter: <?= $this->Html->link('Flow charts', ['controller' => 'Project', 'action' => 'index']) ?></p>
</div>

<script>
(function(){
  const operatorsByCountry = <?= json_encode($operatorOptionsByCountry, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  const productsByOperator = <?= json_encode($productOptionsByOperator, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  const overrideMeta = <?= json_encode($overrideMeta, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;
  const productScopes = <?= json_encode((new \App\Service\OperatorCatalog())->getProductScopes(), JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES) ?>;

  const countrySel = document.getElementById('countrySelect');
  const operatorSel = document.getElementById('operatorSelect');
  const productSel = document.getElementById('productSelect');
  const productList = document.getElementById('productList');
  const productHint = document.getElementById('productHint');
  const scopeField = document.getElementById('scopeField');

  function setOptions(select, optionsMap, emptyLabel) {
    const current = select.value;
    select.innerHTML = '';
    const optEmpty = document.createElement('option');
    optEmpty.value = '';
    optEmpty.textContent = emptyLabel;
    select.appendChild(optEmpty);
    Object.entries(optionsMap).forEach(([value, label]) => {
      const opt = document.createElement('option');
      opt.value = value; opt.textContent = label;
      select.appendChild(opt);
    });
    // restore if still present
    if (current && optionsMap[current]) select.value = current; else select.value = '';
  }

  function setProductList(operatorId) {
    productList.innerHTML = '';
    const list = productsByOperator[operatorId] || [];
    list.forEach(name => {
      const li = document.createElement('li');
      const meta = (overrideMeta[operatorId] && overrideMeta[operatorId][name]) ? overrideMeta[operatorId][name] : null;
      if (meta && (meta.notes || meta.source)) {
        const span = document.createElement('span');
        span.textContent = name;
        span.title = [meta.notes||'', meta.source?`Kilde: ${meta.source}`:''].filter(Boolean).join('\n');
        li.appendChild(span);
      } else {
        li.textContent = name;
      }
      productList.appendChild(li);
    });
    // also fill select options for products
    productSel.innerHTML = '';
    const empty = document.createElement('option'); empty.value = ''; empty.textContent = 'Vælg produkt';
    productSel.appendChild(empty);
    list.forEach(name => {
      const opt = document.createElement('option'); opt.value = name; opt.textContent = name;
      const meta = (overrideMeta[operatorId] && overrideMeta[operatorId][name]) ? overrideMeta[operatorId][name] : null;
      if (meta && (meta.notes || meta.source)) {
        opt.title = [meta.notes||'', meta.source?`Kilde: ${meta.source}`:''].filter(Boolean).join('\n');
      }
      productSel.appendChild(opt);
    });
    productSel.value = '';
    productHint.style.display = 'none';
    productHint.textContent = '';
  }

  countrySel.addEventListener('change', () => {
    const cc = countrySel.value || '';
    const ops = operatorsByCountry[cc] || {};
    setOptions(operatorSel, ops, 'Vælg operatør');
    setProductList('');
  });

  operatorSel.addEventListener('change', () => {
    const op = operatorSel.value || '';
    setProductList(op);
    scopeField.value = '';
  });

  productSel.addEventListener('change', () => {
    const op = operatorSel.value || '';
    const prod = productSel.value || '';
    const meta = (overrideMeta[op] && overrideMeta[op][prod]) ? overrideMeta[op][prod] : null;
    if (meta && (meta.notes || meta.source)) {
      const bits = [];
      if (meta.notes) bits.push(meta.notes);
      if (meta.source) bits.push(`Kilde: ${meta.source}`);
      productHint.textContent = bits.join(' · ');
      productHint.style.display = '';
    } else {
      productHint.textContent = '';
      productHint.style.display = 'none';
    }
    // infer scope
    const inferred = (productScopes[op] && productScopes[op][prod]) ? productScopes[op][prod] : '';
    scopeField.value = inferred;
  });

  // initialize with first country if any
  if (countrySel.options.length > 1) {
    // do nothing; wait for user
  }
})();
</script>
