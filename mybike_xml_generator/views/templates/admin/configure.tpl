<div class="panel">
  <div class="panel-heading"><i class="icon-cogs"></i> MyBike XML Generator</div>

  {foreach from=$confirmations item=conf}
    <div class="alert alert-success">{$conf|escape:'html'}</div>
  {/foreach}
  {foreach from=$errors item=err}
    <div class="alert alert-danger">{$err|escape:'html'}</div>
  {/foreach}

  <ul class="nav nav-tabs" id="mbk-tabs" style="margin-bottom:20px">
    <li class="active"><a href="#mbk-tab-settings"    data-toggle="tab"><i class="icon-cogs"></i> Nustatymai</a></li>
    <li>              <a href="#mbk-tab-categories"   data-toggle="tab"><i class="icon-tags"></i> Kategorijos</a></li>
    <li>              <a href="#mbk-tab-import"       data-toggle="tab"><i class="icon-download"></i> Importas</a></li>
    <li>              <a href="#mbk-tab-vocab"        data-toggle="tab"><i class="icon-book"></i> Žodynas</a></li>
    <li>              <a href="#mbk-tab-xml"          data-toggle="tab"><i class="icon-file-text"></i> XML failai</a></li>
    <li>              <a href="#mbk-tab-log"          data-toggle="tab"><i class="icon-list-alt"></i> Registras</a></li>
  </ul>

  <div class="tab-content">

    {* ================================================================== *}
    {* TAB 1 — NUSTATYMAI *}
    {* ================================================================== *}
    <div class="tab-pane active" id="mbk-tab-settings">

      <h2>API raktas</h2>
      <form method="post" action="{$action_url}">
        <div class="form-group">
          <div class="input-group" style="max-width:520px">
            <input type="text" name="api_key" class="form-control"
                   value="{$api_key|escape:'html'}" placeholder="mbk_...">
            <span class="input-group-btn">
              <button type="submit" name="save_config" class="btn btn-default">Išsaugoti</button>
            </span>
          </div>
        </div>
      </form>

      <h2 style="margin-top:24px">Atsargų filtras</h2>
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
          <p class="help-block" style="margin:4px 0 0 50px">Taikoma API sync užklausoms. Išjungus — sinchronizuojamos visos prekės.</p>
        </div>
        <input type="hidden" name="save_config" value="1">
      </form>

      <h2 style="margin-top:24px">Importo kainodara</h2>
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

    </div>{* /tab-settings *}

    {* ================================================================== *}
    {* TAB 2 — KATEGORIJOS *}
    {* ================================================================== *}
    <div class="tab-pane" id="mbk-tab-categories">

      <h2>XML sinchronizacijos filtras</h2>
      <p class="help-block">Kategorijos įtraukiamos į API sync užklausas (filtruojama serverio pusėje).</p>
      <form method="post" action="{$action_url}">

        {if $categories_empty}
          <p class="alert alert-warning">Kategorijų sąrašas tuščias. Spauskite „Atnaujinti".</p>
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
          <i class="icon-refresh"></i> Atnaujinti sąrašą iš API
        </button>

        <div style="margin-top:16px;padding:10px 14px;background:#f8f8f8;border:1px solid #e0e0e0;border-radius:4px;font-size:12px">
          {if $only_in_stock}<code>in_stock=1</code> įjungtas &nbsp;|&nbsp;{/if}
          {if $categories_empty}
            Kategorijų filtras: visos prekės
          {elseif $categories_enabled_cnt eq 0}
            Kategorijų filtras: visos prekės (nė viena nepasirinkta)
          {else}
            Kategorijų filtras: <strong>{$categories_enabled_cnt}</strong> iš {$categories_total_cnt} kategorijų
            &nbsp;|&nbsp; iš viso prekių: <strong>{$categories_enabled_prods|intval}</strong>
          {/if}
        </div>
      </form>

      <hr style="margin:28px 0">

      <h2>Kategorijų susiejimas: MyBike → PS</h2>
      <p class="help-block">
        Susiekite MyBike kategorijas su PS kategorijomis importo metu.
        Jei nesusieta — prekė importuojama be kategorijos.
      </p>
      <form method="post" action="{$action_url}" style="margin-bottom:8px">
        <button type="submit" name="refresh_category_map" class="btn btn-default btn-sm">
          <i class="icon-refresh"></i> Atnaujinti sąrašą iš XML filtro
        </button>
      </form>

      {if $category_map_empty}
        <p class="alert alert-warning">Susiejimo sąrašas tuščias. Pirmiausia užpildykite XML filtro kategorijas, tada spauskite „Atnaujinti sąrašą".</p>
      {else}
        <form method="post" action="{$action_url}">
          {foreach from=$category_map_grouped key=section item=cats}
            <fieldset style="margin-bottom:16px;border:1px solid #ddd;padding:10px 14px;border-radius:4px">
              <legend style="font-weight:bold;font-size:13px;width:auto;padding:0 6px">{$section|escape:'html'}</legend>
              <table class="table table-condensed" style="margin:0">
                <thead>
                  <tr><th style="width:40%">MyBike kategorija</th><th style="width:10%">Prekių</th><th>PS kategorija</th></tr>
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

    </div>{* /tab-categories *}

    {* ================================================================== *}
    {* TAB 3 — IMPORTAS *}
    {* ================================================================== *}
    <div class="tab-pane" id="mbk-tab-import">

      <h2>Cron token</h2>
      <p class="help-block">Token naudojamas visuose cron URL'uose žemiau. Regeneravus — visi esami URL nustos veikti.</p>
      <form method="post" action="{$action_url}" style="margin-bottom:24px">
        <button type="submit" name="regen_token" class="btn btn-default btn-sm"
                onclick="return confirm('Regeneruoti token? Esami cron URL\'ai nustos veikti.')">
          <i class="icon-refresh"></i>  Regeneruoti token
        </button>
      </form>

      <h2>Duomenų atsisiuntimas iš API</h2>
      <p class="help-block">Parsiunčia produktus iš MyBike API ir išsaugo lentelėje <code>ps_mybike_product</code>.</p>
      <table class="table" style="max-width:900px">
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
            <td><input type="text" class="form-control input-xs" style="width:240px;font-size:11px"
                       value="{$cron_api_sync_full_url|escape:'html'}" readonly onclick="this.select()"></td>
            <td>
              <form method="post" action="{$action_url}">
                <button type="submit" name="run_api_sync_full" class="btn btn-primary btn-sm"
                        onclick="return confirm('Paleisti API full sync? Gali užtrukti kelias minutes.')">
                  <i class="icon-play"></i>  Paleisti
                </button>
              </form>
            </td>
          </tr>
          <tr>
            <td><strong>Stock</strong> (tik atsargos)</td>
            <td colspan="3" class="text-muted" style="font-size:12px">Tas pats laikas kaip full</td>
            <td>—</td>
            <td><input type="text" class="form-control input-xs" style="width:240px;font-size:11px"
                       value="{$cron_api_sync_stock_url|escape:'html'}" readonly onclick="this.select()"></td>
            <td>
              <form method="post" action="{$action_url}">
                <button type="submit" name="run_api_sync_stock" class="btn btn-default btn-sm">
                  <i class="icon-play"></i>  Paleisti
                </button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>
      <p class="help-block">
        Atsisiųsta prekių: <strong>{$staging_count|intval}</strong>.
        <form method="post" action="{$action_url}" style="display:inline;margin-left:12px">
          <button type="submit" name="clear_staging" class="btn btn-danger btn-xs"
                  onclick="return confirm('Išvalyti API duomenis? Visi atsisiųsti duomenys ir PS susiejimai bus prarasti.')">
            <i class="icon-trash"></i> Išvalyti API duomenis
          </button>
        </form>
      </p>

      <hr style="margin:24px 0">

      <h2>Prekių importas į parduotuvę</h2>
      <p class="help-block">Sukuria / atnaujina PrestaShop produktus, kombinacijas, atsargas ir nuotraukas iš atsisiųstų API duomenų.</p>
      <table class="table" style="max-width:900px">
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
            <td><input type="text" class="form-control input-xs" style="width:240px;font-size:11px"
                       value="{$cron_ps_import_url|escape:'html'}" readonly onclick="this.select()"></td>
            <td>
              <form method="post" action="{$action_url}" id="form-ps-import">
                <button type="submit" name="run_ps_import" class="btn btn-primary btn-sm">
                  <i class="icon-play"></i>  Importuoti dabar
                </button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>

      <hr style="margin:24px 0">

      <h2>Test importas (1 prekė)</h2>
      <p class="help-block">
        Importuoja vieną prekę iš atsisiųstų API duomenų (Bikes/E-Bikes — visą spalvos grupę su kombinacijomis).
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
                  onclick="return confirm('Importuoti 1 prekę iš API duomenų?')">
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

    </div>{* /tab-import *}

    {* ================================================================== *}
    {* TAB 4 — ŽODYNAS *}
    {* ================================================================== *}
    <div class="tab-pane" id="mbk-tab-vocab">

      <h2>Specs žodynas</h2>
      <p class="help-block" style="margin-bottom:16px">
        <strong>Filtras</strong> — laukas importuojamas kaip PS prekės savybė (filtruojama kainų naršyklėje).<br>
        <strong>Rodomas</strong> — laukas rodomas <code>specs_full</code> HTML lentelėje produkto aprašyme.<br>
        Laukai, kurių nėra žodyne, į <code>specs</code> neįtraukiami; <code>specs_full</code> rodomi su originaliu raktu.
      </p>

      {* --- Esami įrašai --- *}
      <form method="post" action="{$action_url}">
        <table class="table table-bordered table-condensed" style="font-size:13px">
          <thead>
            <tr>
              <th style="width:160px">API raktas</th>
              <th style="width:160px">EN pavadinimas</th>
              <th style="width:200px">LT pavadinimas</th>
              <th style="width:70px;text-align:center">Filtras</th>
              <th style="width:70px;text-align:center">Rodomas</th>
              <th style="width:70px;text-align:center">Tvarka</th>
              <th style="width:60px"></th>
            </tr>
          </thead>
          <tbody>
            {foreach from=$vocab_entries item=v name=vloop}
            <tr>
              <td>
                <code style="font-size:12px">{$v.api_key|escape:'html'}</code>
                <input type="hidden" name="vocab_api_key[{$smarty.foreach.vloop.index}]" value="{$v.api_key|escape:'html'}">
              </td>
              <td>
                <input type="text" class="form-control input-sm"
                       name="vocab_label_en[{$smarty.foreach.vloop.index}]"
                       value="{$v.label_en|escape:'html'}">
              </td>
              <td>
                <input type="text" class="form-control input-sm"
                       name="vocab_label_lt[{$smarty.foreach.vloop.index}]"
                       value="{$v.label_lt|escape:'html'}">
              </td>
              <td style="text-align:center;vertical-align:middle">
                <input type="checkbox" name="vocab_filterable[{$smarty.foreach.vloop.index}]"
                       value="1" {if $v.filterable}checked{/if}>
              </td>
              <td style="text-align:center;vertical-align:middle">
                <input type="checkbox" name="vocab_show_full[{$smarty.foreach.vloop.index}]"
                       value="1" {if $v.show_full}checked{/if}>
              </td>
              <td style="text-align:center">
                <input type="number" class="form-control input-sm" style="width:60px"
                       name="vocab_sort_order[{$smarty.foreach.vloop.index}]"
                       value="{$v.sort_order|intval}">
              </td>
              <td style="text-align:center;vertical-align:middle">
                <button type="submit" name="delete_vocab_entry" value="1"
                        class="btn btn-danger btn-xs"
                        onclick="this.form.querySelector('[name=delete_api_key]').value='{$v.api_key|escape:'javascript'}';return confirm('Ištrinti: {$v.api_key|escape:'javascript'}?')">
                  <i class="icon-trash"></i>
                </button>
              </td>
            </tr>
            {/foreach}
          </tbody>
        </table>
        <input type="hidden" name="delete_api_key" value="">
        <button type="submit" name="save_vocab" class="btn btn-primary">
          <i class="icon-save"></i> Išsaugoti žodyną
        </button>
      </form>

      {* --- Naujas įrašas --- *}
      <h2 style="margin-top:30px">Pridėti naują lauką</h2>
      <form method="post" action="{$action_url}" style="max-width:780px">
        <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
          <div>
            <label style="font-size:12px;font-weight:600">API raktas</label>
            <input type="text" name="new_api_key" class="form-control input-sm" style="width:140px" placeholder="pvz. stem">
          </div>
          <div>
            <label style="font-size:12px;font-weight:600">EN</label>
            <input type="text" name="new_label_en" class="form-control input-sm" style="width:140px">
          </div>
          <div>
            <label style="font-size:12px;font-weight:600">LT</label>
            <input type="text" name="new_label_lt" class="form-control input-sm" style="width:160px">
          </div>
          <div style="text-align:center">
            <label style="font-size:12px;font-weight:600;display:block">Filtras</label>
            <input type="checkbox" name="new_filterable" value="1">
          </div>
          <div style="text-align:center">
            <label style="font-size:12px;font-weight:600;display:block">Rodomas</label>
            <input type="checkbox" name="new_show_full" value="1" checked>
          </div>
          <div>
            <label style="font-size:12px;font-weight:600">Tvarka</label>
            <input type="number" name="new_sort_order" class="form-control input-sm" style="width:70px" value="999">
          </div>
          <div>
            <button type="submit" name="add_vocab_entry" class="btn btn-success btn-sm">
              <i class="icon-plus"></i> Pridėti
            </button>
          </div>
        </div>
      </form>

    </div>{* /tab-vocab *}

    {* ================================================================== *}
    {* TAB 5 — XML FAILAI *}
    {* ================================================================== *}
    <div class="tab-pane" id="mbk-tab-xml">

      <p class="help-block">XML failai generuojami iš atsisiųstų API duomenų. Prieš generuodami paleiskite duomenų atsisiuntimą iš API.</p>
      <table class="table" style="max-width:960px">
        <thead>
          <tr><th>Failas</th><th>Sugeneruotas</th><th>Dydis</th><th>Produktų</th><th>Trukmė</th><th>Statusas</th><th>Cron URL / Generuoti</th></tr>
        </thead>
        <tbody>
          <tr>
            <td>
              <strong>products_full.xml</strong><br>
              <span class="text-muted" style="font-size:11px">Visi laukai, vienas įrašas/eilutė</span><br>
              <span class="text-muted" style="font-size:11px">+ products_combinations.xml</span>
            </td>
            <td>{$last_full.run}</td>
            <td>
              {$full_xml.size}<br>
              <span class="text-muted" style="font-size:11px">{$combinations_xml.size}</span>
            </td>
            <td>
              {$last_full.count}<br>
              <span class="text-muted" style="font-size:11px">{$last_combinations.count} derinių</span>
            </td>
            <td>{if $last_full.duration neq '—'}{$last_full.duration}s{else}—{/if}</td>
            <td>
              {if $last_full.status eq 'ok'}
                <span class="label label-success">OK</span>
              {elseif $last_full.status neq '—'}
                <span class="label label-danger" title="{$last_full.status|escape:'html'}">Klaida</span>
              {else}—{/if}
            </td>
            <td>
              <input type="text" class="form-control input-xs" style="width:100%;max-width:340px;font-size:11px;margin-bottom:4px"
                     value="{$cron_full_url|escape:'html'}" readonly onclick="this.select()">
              <form method="post" action="{$action_url}">
                <button type="submit" name="run_full" class="btn btn-primary btn-sm">
                  <i class="icon-play"></i> Generuoti dabar
                </button>
              </form>
            </td>
          </tr>
          <tr>
            <td><strong>products_stock.xml</strong><br><span class="text-muted" style="font-size:11px">Tik kaina ir atsargos</span></td>
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
              <input type="text" class="form-control input-xs" style="width:100%;max-width:340px;font-size:11px;margin-bottom:4px"
                     value="{$cron_stock_url|escape:'html'}" readonly onclick="this.select()">
              <form method="post" action="{$action_url}">
                <button type="submit" name="run_stock" class="btn btn-primary btn-sm">
                  <i class="icon-play"></i>  Generuoti dabar
                </button>
              </form>
            </td>
          </tr>
        </tbody>
      </table>

    </div>{* /tab-xml *}

    {* ================================================================== *}
    {* TAB 6 — REGISTRAS *}
    {* ================================================================== *}
    <div class="tab-pane" id="mbk-tab-log">

      <h2>Paskutiniai rezultatai</h2>
      <table class="table table-condensed table-bordered" style="max-width:860px;margin-bottom:24px;font-size:13px">
        <thead>
          <tr style="background:#f5f5f5">
            <th style="width:22%">Veiksmas</th>
            <th style="width:20%">Laikas</th>
            <th>Rezultatas</th>
            <th style="width:9%">Trukmė</th>
            <th style="width:9%">Statusas</th>
          </tr>
        </thead>
        <tbody>
          <tr>
            <td><strong>API Sync</strong></td>
            <td class="text-muted">{$last_api_sync.run}</td>
            <td>{$last_api_sync.count} prekių</td>
            <td>{if $last_api_sync.duration neq '—'}{$last_api_sync.duration}s{else}—{/if}</td>
            <td>
              {if $last_api_sync.status|substr:0:2 eq 'ok'}<span class="label label-success">OK</span>
              {elseif $last_api_sync.status neq '—'}<span class="label label-danger" title="{$last_api_sync.status|escape:'html'}">Klaida</span>
              {else}—{/if}
            </td>
          </tr>
          <tr>
            <td><strong>PS Importas</strong></td>
            <td class="text-muted">{$last_import.run}</td>
            <td>
              {if $last_import.imported neq '—'}
                <span title="Nauji">&#x2795; {$last_import.imported}</span>
                &nbsp; <span class="text-muted" title="Atnaujinta">&#x21BB; {$last_import.updated}</span>
                &nbsp; <span class="text-muted" title="Praleista">&#x23E9; {$last_import.skipped}</span>
              {else}—{/if}
            </td>
            <td>{if $last_import.duration neq '—'}{$last_import.duration}s{else}—{/if}</td>
            <td>
              {if $last_import.status eq 'ok'}<span class="label label-success">OK</span>
              {elseif $last_import.status neq '—'}<span class="label label-danger" title="{$last_import.status|escape:'html'}">Klaida</span>
              {else}—{/if}
            </td>
          </tr>
          <tr>
            <td><strong>XML Full + Combinations</strong></td>
            <td class="text-muted">{$last_full.run}</td>
            <td>{$last_full.count} produktų / {$last_combinations.count} derinių</td>
            <td>{if $last_full.duration neq '—'}{$last_full.duration}s{else}—{/if}</td>
            <td>
              {if $last_full.status eq 'ok'}<span class="label label-success">OK</span>
              {elseif $last_full.status neq '—'}<span class="label label-danger" title="{$last_full.status|escape:'html'}">Klaida</span>
              {else}—{/if}
            </td>
          </tr>
          <tr>
            <td><strong>XML Stock</strong></td>
            <td class="text-muted">{$last_stock.run}</td>
            <td>{$last_stock.count} produktų</td>
            <td>{if $last_stock.duration neq '—'}{$last_stock.duration}s{else}—{/if}</td>
            <td>
              {if $last_stock.status eq 'ok'}<span class="label label-success">OK</span>
              {elseif $last_stock.status neq '—'}<span class="label label-danger" title="{$last_stock.status|escape:'html'}">Klaida</span>
              {else}—{/if}
            </td>
          </tr>
        </tbody>
      </table>

      <h2>Log failai</h2>
      <p class="help-block" style="margin-top:-6px">
        Rotuojami automatiškai pasiekus 1 MB (pervardijamas į <code>.old</code>). Rodomos paskutinės 500 eilučių.
      </p>

      {* --- API Sync log --- *}
      <div style="margin-bottom:28px">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:6px">
          <h2 style="margin:0">API Sync</h2>
          {if $log_api_sync.exists}
            <span class="text-muted" style="font-size:12px">
              {$log_api_sync.size} &nbsp;|&nbsp; {$log_api_sync.modified}
              {if $log_api_sync.truncated}&nbsp;|&nbsp; <em>paskutinės 500 iš {$log_api_sync.total_lines} eilučių</em>
              {else}&nbsp;|&nbsp; {$log_api_sync.total_lines} eilučių{/if}
            </span>
            <form method="post" action="{$action_url}" style="margin:0">
              <input type="hidden" name="clear_log_which" value="api_sync">
              <button type="submit" name="clear_log" class="btn btn-danger btn-xs"
                      onclick="return confirm('Išvalyti API Sync log failą?')">
                <i class="icon-trash"></i> Išvalyti
              </button>
            </form>
          {else}<span class="text-muted" style="font-size:12px">Failas neegzistuoja</span>{/if}
        </div>
        {if $log_api_sync.exists && $log_api_sync.content}
          <pre style="max-height:320px;overflow-y:auto;font-size:11px;background:#1e1e1e;color:#d4d4d4;padding:10px;border-radius:4px;border:none;white-space:pre-wrap;word-break:break-all">{$log_api_sync.content|escape:'html'}</pre>
        {elseif $log_api_sync.exists}
          <p class="text-muted" style="font-size:12px;font-style:italic">Log failas tuščias.</p>
        {/if}
      </div>

      {* --- PS Importas log --- *}
      <div style="margin-bottom:28px">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:6px">
          <h2 style="margin:0">PS Importas</h2>
          {if $log_ps_import.exists}
            <span class="text-muted" style="font-size:12px">
              {$log_ps_import.size} &nbsp;|&nbsp; {$log_ps_import.modified}
              {if $log_ps_import.truncated}&nbsp;|&nbsp; <em>paskutinės 500 iš {$log_ps_import.total_lines} eilučių</em>
              {else}&nbsp;|&nbsp; {$log_ps_import.total_lines} eilučių{/if}
            </span>
            <form method="post" action="{$action_url}" style="margin:0">
              <input type="hidden" name="clear_log_which" value="ps_import">
              <button type="submit" name="clear_log" class="btn btn-danger btn-xs"
                      onclick="return confirm('Išvalyti PS Importas log failą?')">
                <i class="icon-trash"></i> Išvalyti
              </button>
            </form>
          {else}<span class="text-muted" style="font-size:12px">Failas neegzistuoja</span>{/if}
        </div>
        {if $log_ps_import.exists && $log_ps_import.content}
          <pre style="max-height:320px;overflow-y:auto;font-size:11px;background:#1e1e1e;color:#d4d4d4;padding:10px;border-radius:4px;border:none;white-space:pre-wrap;word-break:break-all">{$log_ps_import.content|escape:'html'}</pre>
        {elseif $log_ps_import.exists}
          <p class="text-muted" style="font-size:12px;font-style:italic">Log failas tuščias.</p>
        {/if}
      </div>

      {* --- XML generavimas log --- *}
      <div style="margin-bottom:28px">
        <div style="display:flex;align-items:center;gap:16px;margin-bottom:6px">
          <h2 style="margin:0">XML generavimas</h2>
          {if $log_xml.exists}
            <span class="text-muted" style="font-size:12px">
              {$log_xml.size} &nbsp;|&nbsp; {$log_xml.modified}
              {if $log_xml.truncated}&nbsp;|&nbsp; <em>paskutinės 500 iš {$log_xml.total_lines} eilučių</em>
              {else}&nbsp;|&nbsp; {$log_xml.total_lines} eilučių{/if}
            </span>
            <form method="post" action="{$action_url}" style="margin:0">
              <input type="hidden" name="clear_log_which" value="xml">
              <button type="submit" name="clear_log" class="btn btn-danger btn-xs"
                      onclick="return confirm('Išvalyti XML generavimas log failą?')">
                <i class="icon-trash"></i> Išvalyti
              </button>
            </form>
          {else}<span class="text-muted" style="font-size:12px">Failas neegzistuoja</span>{/if}
        </div>
        {if $log_xml.exists && $log_xml.content}
          <pre style="max-height:320px;overflow-y:auto;font-size:11px;background:#1e1e1e;color:#d4d4d4;padding:10px;border-radius:4px;border:none;white-space:pre-wrap;word-break:break-all">{$log_xml.content|escape:'html'}</pre>
        {elseif $log_xml.exists}
          <p class="text-muted" style="font-size:12px;font-style:italic">Log failas tuščias.</p>
        {/if}
      </div>


    </div>{* /tab-log *}

  </div>{* /tab-content *}
</div>

{* ================================================================== *}
{* IMPORTO PROGRESO MODALAS *}
{* ================================================================== *}
<div class="modal fade" id="mbk-import-modal" tabindex="-1" role="dialog"
     data-backdrop="static" data-keyboard="false">
  <div class="modal-dialog" style="max-width:480px" role="document">
    <div class="modal-content">
      <div class="modal-header" style="padding:12px 16px;border-bottom:1px solid #e5e5e5">
        <h4 class="modal-title">
          <i class="icon-refresh icon-spin"></i>&nbsp; PS Importas vykdomas...
        </h4>
      </div>
      <div class="modal-body" style="padding:20px 20px 16px">
        <div class="progress" style="margin-bottom:12px;height:22px;border-radius:4px">
          <div id="mbk-import-bar"
               class="progress-bar progress-bar-striped active"
               role="progressbar"
               style="width:2%;min-width:2%;height:22px;line-height:22px;transition:width 0.5s ease;font-size:12px">
            0%
          </div>
        </div>
        <p id="mbk-import-step"
           class="text-muted"
           style="font-size:12px;margin:0;min-height:18px;word-break:break-word"></p>
      </div>
    </div>
  </div>
</div>

<script>
var mbkImportStatusUrl = '{$ajax_import_status_url|escape:"javascript"}';
{literal}
(function () {
    var pollTimer = null;

    function updateBar(pct, step, status) {
        var bar  = document.getElementById('mbk-import-bar');
        var text = document.getElementById('mbk-import-step');
        if (bar) {
            var p = Math.max(2, pct || 0);
            bar.style.width   = p + '%';
            bar.textContent   = pct + '%';
        }
        if (text) {
            text.textContent = step || '';
        }
        if (status === 'done' && bar) {
            bar.classList.remove('active', 'progress-bar-striped', 'progress-bar-danger');
            bar.classList.add('progress-bar-success');
            bar.style.width  = '100%';
            bar.textContent  = '100%';
            var title = document.querySelector('#mbk-import-modal .modal-title');
            if (title) { title.innerHTML = '<i class="icon-ok"></i>&nbsp; Importas atliktas!'; }
        }
        if (status === 'error' && bar) {
            bar.classList.remove('active', 'progress-bar-success');
            bar.classList.add('progress-bar-danger');
            var title = document.querySelector('#mbk-import-modal .modal-title');
            if (title) { title.innerHTML = '<i class="icon-remove"></i>&nbsp; Importo klaida'; }
        }
    }

    function stopPolling() {
        if (pollTimer) { clearInterval(pollTimer); pollTimer = null; }
    }

    function startPolling() {
        pollTimer = setInterval(function () {
            fetch(mbkImportStatusUrl, { cache: 'no-store' })
                .then(function (r) { return r.json(); })
                .then(function (d) {
                    updateBar(d.percent || 0, d.step || '', d.status);
                    if (d.status === 'done' || d.status === 'error') {
                        stopPolling();
                    }
                })
                .catch(function () { /* ignore — page may be unloading */ });
        }, 2000);
    }

    var form = document.getElementById('form-ps-import');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (!confirm('Paleisti PS importą? Gali užtrukti ilgai (iki 30 min 28k prekių).')) {
                e.preventDefault();
                return;
            }
            $('#mbk-import-modal').modal('show');
            updateBar(0, 'Jungiamasi...', 'running');
            // Small delay so modal renders before first poll
            setTimeout(startPolling, 1500);
        });
    }

    // Tab persistence
    var tabKey = 'mbk_active_tab';
    var saved = localStorage.getItem(tabKey);
    if (saved) {
        var el = document.querySelector('#mbk-tabs a[href="' + saved + '"]');
        if (el) { $(el).tab('show'); }
    }
    $('#mbk-tabs a').on('shown.bs.tab', function (e) {
        localStorage.setItem(tabKey, e.target.getAttribute('href'));
    });
}());
{/literal}
</script>
