<div class="panel">
  <div class="panel-heading"><i class="icon-cogs"></i> MyBike XML Generator</div>

  {foreach from=$confirmations item=conf}
    <div class="alert alert-success">{$conf|escape:'html'}</div>
  {/foreach}
  {foreach from=$errors item=err}
    <div class="alert alert-danger">{$err|escape:'html'}</div>
  {/foreach}

  <h4>Konfigūracija</h4>
  <form method="post" action="{$action_url}">
    <div class="form-group">
      <label>API raktas</label>
      <div class="input-group" style="max-width:520px">
        <input type="text" name="api_key" class="form-control"
               value="{$api_key|escape:'html'}" placeholder="mbk_...">
        <span class="input-group-btn">
          <button type="submit" name="save_config" class="btn btn-default">Išsaugoti</button>
        </span>
      </div>
    </div>
  </form>

  <h4 style="margin-top:24px">Cron URL'ai</h4>
  <div class="form-group">
    <label>Full sync (1&times;/parą):</label>
    <input type="text" class="form-control" style="max-width:640px"
           value="{$cron_full_url|escape:'html'}" readonly onclick="this.select()">
  </div>
  <div class="form-group">
    <label>Stock sync (1&times;/valandą):</label>
    <input type="text" class="form-control" style="max-width:640px"
           value="{$cron_stock_url|escape:'html'}" readonly onclick="this.select()">
  </div>
  <form method="post" action="{$action_url}" style="margin-top:6px">
    <button type="submit" name="regen_token" class="btn btn-default btn-sm"
            onclick="return confirm('Regeneruoti token? Esami cron URL\'ai nustos veikti.')">
      <i class="icon-refresh"></i> Regeneruoti token
    </button>
  </form>

  <h4 style="margin-top:24px">Atsargų filtras</h4>
  <form method="post" action="{$action_url}">
    <div class="form-group" style="margin-bottom:8px">
      <label class="switch-label" style="display:flex;align-items:center;gap:10px;cursor:pointer;font-weight:normal">
        <span style="position:relative;display:inline-block;width:40px;height:22px;flex-shrink:0">
          <input type="checkbox" name="only_in_stock" value="1"
                 {if $only_in_stock}checked{/if}
                 style="opacity:0;width:0;height:0;position:absolute"
                 onchange="this.form.submit()">
          <span style="position:absolute;inset:0;background:{if $only_in_stock}#25b9d7{else}#ccc{/if};border-radius:22px;transition:.2s"></span>
          <span style="position:absolute;left:{if $only_in_stock}20px{else}2px{/if};top:2px;width:18px;height:18px;background:#fff;border-radius:50%;transition:.2s"></span>
        </span>
        <span>Rodyti tik prekes su atsargomis (<code>in_stock=1</code>)</span>
      </label>
      <p class="help-block" style="margin:4px 0 0 50px">Įjungus — API grąžins tik turimas prekes. Išjungus — visos prekės (įskaitant neturimias).</p>
    </div>
    <input type="hidden" name="save_config" value="1">
  </form>

  <h4 style="margin-top:24px">Kategorijų filtras</h4>
  <form method="post" action="{$action_url}">
    <p class="help-block">Pasirinkite, kurių kategorijų prekes įtraukti į XML failus. Jei nepasirinkta nei viena — įtraukiamos visos.</p>

    {if $categories_empty}
      <p class="alert alert-warning">Kategorijų sąrašas tuščias. Spauskite „Atnaujinti", kad gautumėte sąrašą iš API.</p>
    {else}
      {foreach from=$categories_grouped key=section item=cats}
        {assign var=sectionId value=$section|lower|replace:' ':'-'|replace:"'":''}
        <fieldset style="margin-bottom:16px;border:1px solid #ddd;padding:10px 14px;border-radius:4px">
          <legend style="font-weight:bold;font-size:13px;width:auto;padding:0 6px">
            {$section|escape:'html'}
            <a href="#" style="font-weight:normal;font-size:11px;margin-left:8px"
               onclick="mbkToggleSection('{$sectionId}', true); return false;">visos</a>
            <span style="color:#ccc">|</span>
            <a href="#" style="font-weight:normal;font-size:11px"
               onclick="mbkToggleSection('{$sectionId}', false); return false;">nė viena</a>
          </legend>
          <div id="mbk-section-{$sectionId}" style="display:flex;flex-wrap:wrap;gap:6px 24px">
            {foreach from=$cats item=cat}
              <label style="font-weight:normal;min-width:220px">
                <input type="checkbox" name="enabled_categories[]"
                       value="{$cat.id_category|intval}"
                       {if $cat.enabled}checked{/if}>
                {$cat.title|escape:'html'}
                <span class="text-muted" style="font-size:11px">({$cat.product_count})</span>
              </label>
            {/foreach}
          </div>
        </fieldset>
      {/foreach}
      <script>
      function mbkToggleSection(sectionId, checked) {
          var boxes = document.querySelectorAll('#mbk-section-' + sectionId + ' input[type="checkbox"]');
          for (var i = 0; i < boxes.length; i++) { boxes[i].checked = checked; }
      }
      </script>
      <button type="submit" name="save_categories" class="btn btn-success">
        <i class="icon-save"></i> Išsaugoti pasirinkimą
      </button>
    {/if}

    <button type="submit" name="refresh_categories" class="btn btn-default" style="margin-left:8px"
            onclick="return confirm('Atnaujinti kategorijų sąrašą iš API?')">
      <i class="icon-refresh"></i> Atnaujinti kategorijų sąrašą
    </button>

    <div style="margin-top:20px;padding:12px 16px;background:#f8f8f8;border:1px solid #e0e0e0;border-radius:4px;font-size:12px">
      <strong style="font-size:13px">Aktyvūs sinchronizacijos parametrai</strong>
      <table style="margin-top:8px;border-collapse:collapse;width:100%;max-width:560px">
        <tr>
          <td style="padding:3px 12px 3px 0;color:#666;white-space:nowrap">Atsargų filtras</td>
          <td>
            {if $only_in_stock}
              <code>in_stock=1</code> <span class="label label-success" style="font-size:11px">įjungtas</span>
            {else}
              <span class="text-muted">—&nbsp;nefiltruojama</span>
            {/if}
          </td>
        </tr>
        <tr>
          <td style="padding:3px 12px 3px 0;color:#666;white-space:nowrap">Kategorijų filtras</td>
          <td>
            {if $categories_empty}
              <span class="text-muted">— kategorijų sąrašas tuščias, visos prekės</span>
            {elseif $categories_enabled_cnt eq 0}
              <span class="text-muted">— nė viena nepasirinkta, visos prekės</span>
            {elseif $categories_enabled_cnt eq $categories_total_cnt}
              <span class="text-muted">— visos {$categories_total_cnt} kategorijos</span>
            {else}
              <strong>{$categories_enabled_cnt}</strong> iš {$categories_total_cnt} kategorijų
            {/if}
          </td>
        </tr>
        <tr>
          <td style="padding:3px 12px 3px 0;color:#666;white-space:nowrap">Puslapiavimas</td>
          <td><code>limit=100</code>, iteruojami visi puslapiai</td>
        </tr>
        <tr>
          <td style="padding:6px 12px 0 0;color:#666;white-space:nowrap;vertical-align:top">Pavyzdinė užklausa</td>
          <td style="padding-top:6px">
            <code>GET /api/v1/products?page=1&amp;limit=100{if $only_in_stock}&amp;in_stock=1{/if}</code>
            {if not $categories_empty and $categories_enabled_cnt gt 0 and $categories_enabled_cnt lt $categories_total_cnt}
              <br><span class="text-muted" style="font-size:11px">+ lokalus filtravimas pagal category_id</span>
            {/if}
          </td>
        </tr>
      </table>
    </div>
  </form>

  {* ------------------------------------------------------------------ *}
  {* v2: IMPORTO KONFIGŪRACIJA *}
  {* ------------------------------------------------------------------ *}
  <h4 style="margin-top:32px">v2 — Importo kainodara</h4>
  <form method="post" action="{$action_url}">
    <div style="display:flex;flex-wrap:wrap;gap:20px;align-items:flex-end;max-width:780px">
      <div class="form-group" style="margin:0">
        <label>Kainos laukas</label>
        <select name="import_price_key" class="form-control" style="width:160px">
          <option value="price"      {if $import_price_key eq 'price'}selected{/if}>price (dilerio)</option>
          <option value="base_price" {if $import_price_key eq 'base_price'}selected{/if}>base_price (MSRP)</option>
        </select>
      </div>
      <div class="form-group" style="margin:0">
        <label>Koeficientas</label>
        <input type="text" name="import_coefficient" class="form-control" style="width:100px"
               value="{$import_coefficient|escape:'html'}">
      </div>
      <div class="form-group" style="margin:0">
        <label style="display:block">Su PVM?</label>
        <label style="font-weight:normal;display:flex;align-items:center;gap:6px;margin-top:6px">
          <input type="checkbox" name="import_with_vat" value="1" {if $import_with_vat}checked{/if}>
          API kaina su PVM
        </label>
      </div>
      <div class="form-group" style="margin:0">
        <label>PVM grupė (PS)</label>
        <select name="import_tax_rules_id" class="form-control" style="width:200px">
          <option value="0">— nėra —</option>
          {foreach from=$tax_rules_groups item=trg}
            <option value="{$trg.id_tax_rules_group|intval}"
              {if $import_tax_rules_id eq $trg.id_tax_rules_group}selected{/if}>
              {$trg.name|escape:'html'}
            </option>
          {/foreach}
        </select>
      </div>
      <div style="margin:0">
        <button type="submit" name="save_import_config" class="btn btn-success">
          <i class="icon-save"></i> Išsaugoti
        </button>
      </div>
    </div>
    <p class="help-block" style="margin-top:8px">
      PS kaina = kainos_laukas × koeficientas {ldelim}÷ (1 + PVM%) jei su PVM{rdelim}.
      <code>wholesale_price</code> = kitas kainos laukas, be koeficiento.
    </p>
  </form>

  {* ------------------------------------------------------------------ *}
  {* v2: KATEGORIJŲ SUSIEJIMAS *}
  {* ------------------------------------------------------------------ *}
  <h4 style="margin-top:32px">v2 — Kategorijų susiejimas (MyBike → PS)</h4>
  <p class="help-block">
    Susiekite MyBike kategorijas su PS kategorijomis. Jei nesusieta — prekė importuojama be kategorijos.
    Pirmiausia paspauskite „Atnaujinti sąrašą", kad užpildytumėte lentelę iš kategorijų filtro.
  </p>
  <form method="post" action="{$action_url}" style="margin-bottom:8px">
    <button type="submit" name="refresh_category_map" class="btn btn-default btn-sm">
      <i class="icon-refresh"></i> Atnaujinti sąrašą iš kategorijų filtro
    </button>
  </form>

  {if $category_map_empty}
    <p class="alert alert-warning">Susiejimo sąrašas tuščias. Pirmiausia užpildykite kategorijų filtrą (žemiau), tada spauskite „Atnaujinti sąrašą".</p>
  {else}
    <form method="post" action="{$action_url}">
      {foreach from=$category_map_grouped key=section item=cats}
        <fieldset style="margin-bottom:16px;border:1px solid #ddd;padding:10px 14px;border-radius:4px">
          <legend style="font-weight:bold;font-size:13px;width:auto;padding:0 6px">{$section|escape:'html'}</legend>
          <table class="table table-condensed" style="margin:0">
            <thead>
              <tr>
                <th style="width:40%">MyBike kategorija</th>
                <th style="width:10%">Prekių</th>
                <th>PS kategorija</th>
              </tr>
            </thead>
            <tbody>
              {foreach from=$cats item=cat}
                <tr>
                  <td>{$cat.mybike_category|escape:'html'}</td>
                  <td class="text-muted">{$cat.mybike_product_count|intval}</td>
                  <td>
                    <select name="category_map[{$cat.mybike_category_id|intval}]"
                            class="form-control input-sm" style="max-width:320px">
                      <option value="">— nesusieta —</option>
                      {foreach from=$ps_categories item=pscat}
                        <option value="{$pscat.id_category|intval}"
                          {if $cat.ps_id_category eq $pscat.id_category}selected{/if}>
                          {$pscat.name|escape:'html'}
                        </option>
                      {/foreach}
                    </select>
                  </td>
                </tr>
              {/foreach}
            </tbody>
          </table>
        </fieldset>
      {/foreach}
      <button type="submit" name="save_category_map" class="btn btn-success">
        <i class="icon-save"></i> Išsaugoti susiejimą
      </button>
    </form>
  {/if}

  {* ------------------------------------------------------------------ *}
  {* v2: API SINCHRONIZACIJA *}
  {* ------------------------------------------------------------------ *}
  <h4 style="margin-top:32px">v2 — API sinchronizacija (API → staging DB)</h4>
  <p class="help-block">Parsiunčia produktus iš MyBike API ir išsaugo lentelėje <code>ps_mybike_product</code>.</p>
  <table class="table" style="max-width:860px">
    <thead>
      <tr><th>Režimas</th><th>Paskutinis</th><th>Prekių</th><th>Trukmė</th><th>Statusas</th><th>Cron URL</th><th></th></tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>Full</strong> (su detail)</td>
        <td>{$last_api_sync.run}</td>
        <td>{$last_api_sync.count}</td>
        <td>{if $last_api_sync.duration neq '—'}{$last_api_sync.duration}s{else}—{/if}</td>
        <td>
          {if $last_api_sync.status|substr:0:2 eq 'ok'}
            <span class="label label-success">OK</span>
          {elseif $last_api_sync.status neq '—'}
            <span class="label label-danger" title="{$last_api_sync.status|escape:'html'}">Klaida</span>
          {else}—{/if}
        </td>
        <td><input type="text" class="form-control input-xs" style="width:280px;font-size:11px"
                   value="{$cron_api_sync_full_url|escape:'html'}" readonly onclick="this.select()"></td>
        <td>
          <form method="post" action="{$action_url}">
            <button type="submit" name="run_api_sync_full" class="btn btn-primary btn-sm"
                    onclick="return confirm('Paleisti API full sync? Gali užtrukti kelias minutes.')">
              <i class="icon-play"></i> Paleisti
            </button>
          </form>
        </td>
      </tr>
      <tr>
        <td><strong>Stock</strong> (tik atsargos)</td>
        <td colspan="3" class="text-muted" style="font-size:12px">Tas pats laikas kaip full</td>
        <td>—</td>
        <td><input type="text" class="form-control input-xs" style="width:280px;font-size:11px"
                   value="{$cron_api_sync_stock_url|escape:'html'}" readonly onclick="this.select()"></td>
        <td>
          <form method="post" action="{$action_url}">
            <button type="submit" name="run_api_sync_stock" class="btn btn-default btn-sm">
              <i class="icon-play"></i> Paleisti
            </button>
          </form>
        </td>
      </tr>
    </tbody>
  </table>
  <p class="help-block">Staging lentelėje šiuo metu: <strong>{$staging_count|intval}</strong> įrašų.
    <form method="post" action="{$action_url}" style="display:inline;margin-left:12px">
      <button type="submit" name="clear_staging" class="btn btn-danger btn-xs"
              onclick="return confirm('Išvalyti staging lentelę? Visi API duomenys bus ištrinti ir PS susiejimai (ps_id_product) nebus matomi iki kito importo.')">
        <i class="icon-trash"></i> Išvalyti staging
      </button>
    </form>
  </p>

  {* ------------------------------------------------------------------ *}
  {* v2: PS IMPORTAS *}
  {* ------------------------------------------------------------------ *}
  <h4 style="margin-top:32px">v2 — PS importas (staging → PrestaShop)</h4>
  <p class="help-block">Sukuria / atnaujina PS produktus, kombinacijas, atsargas ir nuotraukas iš staging lentelės.</p>
  <table class="table" style="max-width:860px">
    <thead>
      <tr><th>Paskutinis</th><th>Nauji</th><th>Atnaujinta</th><th>Praleista</th><th>Trukmė</th><th>Statusas</th><th>Cron URL</th><th></th></tr>
    </thead>
    <tbody>
      <tr>
        <td>{$last_import.run}</td>
        <td>{$last_import.imported}</td>
        <td>{$last_import.updated}</td>
        <td>{$last_import.skipped}</td>
        <td>{if $last_import.duration neq '—'}{$last_import.duration}s{else}—{/if}</td>
        <td>
          {if $last_import.status eq 'ok'}
            <span class="label label-success">OK</span>
          {elseif $last_import.status neq '—'}
            <span class="label label-danger" title="{$last_import.status|escape:'html'}">Klaida</span>
          {else}—{/if}
        </td>
        <td><input type="text" class="form-control input-xs" style="width:280px;font-size:11px"
                   value="{$cron_ps_import_url|escape:'html'}" readonly onclick="this.select()"></td>
        <td>
          <form method="post" action="{$action_url}">
            <button type="submit" name="run_ps_import" class="btn btn-primary btn-sm"
                    onclick="return confirm('Paleisti PS importą? Gali užtrukti ilgai (iki 30 min 28k prekių).')">
              <i class="icon-play"></i> Importuoti dabar
            </button>
          </form>
        </td>
      </tr>
    </tbody>
  </table>

  {* ------------------------------------------------------------------ *}
  {* v2: TEST IMPORTAS *}
  {* ------------------------------------------------------------------ *}
  <h4 style="margin-top:24px">v2 — Test importas (1 prekė)</h4>
  <p class="help-block">
    Importuoja vieną staging įrašą (Bikes/E-Bikes — visą spalvos grupę su visomis dydžio kombinacijomis).
    Jei ID nenurodytas — naudojamas pirmas.
  </p>
  <form method="post" action="{$action_url}" style="display:flex;align-items:flex-end;gap:10px;flex-wrap:wrap">
    <div class="form-group" style="margin:0">
      <label>mybike_id</label>
      <input type="number" name="test_mybike_id" class="form-control" style="width:140px"
             placeholder="(pirmas)" min="1">
    </div>
    <div style="margin:0">
      <button type="submit" name="run_ps_import_test" class="btn btn-default btn-sm"
              onclick="return confirm('Importuoti 1 prekę iš staging?')">
        <i class="icon-play"></i> Testuoti
      </button>
    </div>
  </form>
  {if $last_test_import.run}
    <div style="margin-top:10px;padding:10px 14px;background:#f8f8f8;border:1px solid #e0e0e0;border-radius:4px;font-size:12px">
      <strong>Paskutinis testas:</strong> {$last_test_import.run|escape:'html'} —
      {if $last_test_import.status eq 'ok'}
        <span class="label label-success">OK</span>
      {elseif $last_test_import.status}
        <span class="label label-danger" title="{$last_test_import.status|escape:'html'}">Klaida</span>
      {/if}
      {if $last_test_import.detail}
        <code style="display:block;margin-top:6px;font-size:11px;word-break:break-all">{$last_test_import.detail|escape:'html'}</code>
      {/if}
    </div>
  {/if}

  <h4 style="margin-top:24px">XML failai</h4>
  <table class="table" style="max-width:900px">
    <thead>
      <tr>
        <th>Failas</th>
        <th>Sugeneruotas</th>
        <th>Dydis</th>
        <th>Produktų</th>
        <th>Trukmė</th>
        <th>Statusas</th>
        <th></th>
      </tr>
    </thead>
    <tbody>
      <tr>
        <td><strong>products_full.xml</strong></td>
        <td>{$last_full.run}</td>
        <td>{$full_xml.size}</td>
        <td>{$last_full.count}</td>
        <td>{if $last_full.duration neq '—'}{$last_full.duration}s{else}—{/if}</td>
        <td>
          {if $last_full.status eq 'ok'}
            <span class="label label-success">OK</span>
          {elseif $last_full.status neq '—'}
            <span class="label label-danger" title="{$last_full.status|escape:'html'}">Klaida</span>
          {else}—{/if}
        </td>
        <td>
          <form method="post" action="{$action_url}">
            <button type="submit" name="run_full" class="btn btn-primary btn-sm"
                    onclick="return confirm('Generuoti products_full.xml? Gali užtrukti kelias minutes.')">
              <i class="icon-play"></i> Generuoti dabar
            </button>
          </form>
        </td>
      </tr>
      <tr>
        <td><strong>products_stock.xml</strong></td>
        <td>{$last_stock.run}</td>
        <td>{$stock_xml.size}</td>
        <td>{$last_stock.count}</td>
        <td>{if $last_stock.duration neq '—'}{$last_stock.duration}s{else}—{/if}</td>
        <td>
          {if $last_stock.status eq 'ok'}
            <span class="label label-success">OK</span>
          {elseif $last_stock.status neq '—'}
            <span class="label label-danger" title="{$last_stock.status|escape:'html'}">Klaida</span>
          {else}—{/if}
        </td>
        <td>
          <form method="post" action="{$action_url}">
            <button type="submit" name="run_stock" class="btn btn-primary btn-sm">
              <i class="icon-play"></i> Generuoti dabar
            </button>
          </form>
        </td>
      </tr>
    </tbody>
  </table>
</div>
