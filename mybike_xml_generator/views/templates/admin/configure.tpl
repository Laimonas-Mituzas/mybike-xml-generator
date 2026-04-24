{extends file='helper/form/form.tpl'}

{block name="content"}
<div class="panel">
  <div class="panel-heading"><i class="icon-cogs"></i> MyBike XML Generator</div>

  {* Pranešimai *}
  {foreach from=$confirmations item=conf}
    <div class="alert alert-success">{$conf|escape:'html'}</div>
  {/foreach}
  {foreach from=$errors item=err}
    <div class="alert alert-danger">{$err|escape:'html'}</div>
  {/foreach}

  {* Konfigūracija *}
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

  {* Cron URL'ai *}
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

  {* XML failai *}
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

      {* Full XML *}
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

      {* Stock XML *}
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
{/block}
