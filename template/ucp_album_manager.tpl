{* Album manager partial: injected into profile form by JS. No <form> tag here. *}
{if isset($UCP_ALBUMS) && $UCP_ALBUMS|@count > 0}
  <div class="cpt-album-manager" aria-describedby="cpt-album-manager-help">
    <p id="cpt-album-manager-help" class="cpt-help">{'Edit your galleries. Changes are saved with the main form.'|@translate}</p>
    <ul class="cpt-album-list">
    {foreach from=$UCP_ALBUMS item=ALBUM}
      <li class="cpt-album-row" data-album-id="{$ALBUM.id|escape}">
        <fieldset class="cpt-fieldset">
          <legend class="cpt-legend">{'Album'|@translate} #{$ALBUM.id|escape}: {$ALBUM.name|escape}</legend>
          <div class="cpt-field">
            <label for="cpt-name-{$ALBUM.id|escape}">{'Name'|@translate}</label>
            <input type="text" id="cpt-name-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][name]" value="{$ALBUM.name|escape}" />
          </div>
          <div class="cpt-field">
            <label for="cpt-comment-{$ALBUM.id|escape}">{'Description'|@translate}</label>
            <textarea id="cpt-comment-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][comment]" rows="2">{$ALBUM.comment|escape}</textarea>
          </div>
          <div class="cpt-field cpt-inline">
            <input type="checkbox" id="cpt-private-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][private]" {if $ALBUM.status=='private'}checked{/if} />
            <label for="cpt-private-{$ALBUM.id|escape}">{'Make this gallery private'|@translate}</label>
          </div>
        </fieldset>
      </li>
    {/foreach}
    </ul>
  </div>
{/if}
