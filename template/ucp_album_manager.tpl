{* Album manager partial: injected into profile form by JS. No <form> tag here. *}
<div class="cpt-album-manager" aria-describedby="cpt-album-manager-help">
  <div class="card mb-3">
    <h4 class="card-header">{'My Galleries'|@translate}</h4>
    <div class="card-body">
      <p id="cpt-album-manager-help" class="cpt-help text-muted small mb-3">{'Edit your galleries and save them here.'|@translate}</p>
      <div class="cpt-status alert" role="status" aria-live="polite" hidden></div>

      {if isset($UCP_ALBUMS) && $UCP_ALBUMS|@count > 0}
        {foreach from=$UCP_ALBUMS item=ALBUM}
          <div class="cpt-album card border-secondary mb-4" data-album-id="{$ALBUM.id|escape}">
            <div class="card-header py-2 px-3">
              <strong>{'Album'|@translate} #{$ALBUM.id|escape}:</strong> {$ALBUM.name|escape}
            </div>
            <div class="card-body pt-3 pb-2">
              <div class="form-group row align-items-center">
                <label for="cpt-name-{$ALBUM.id|escape}" class="col-12 col-md-3 col-form-label">{'Name'|@translate}</label>
                <div class="col-12 col-md-7">
                  <input type="text" class="form-control" id="cpt-name-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][name]" value="{$ALBUM.name|escape}" />
                </div>
              </div>
              <div class="form-group row">
                <label for="cpt-comment-{$ALBUM.id|escape}" class="col-12 col-md-3 col-form-label">{'Description'|@translate}</label>
                <div class="col-12 col-md-7">
                  <textarea class="form-control" id="cpt-comment-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][comment]" rows="2">{$ALBUM.comment|escape}</textarea>
                </div>
              </div>
              <div class="form-group row mb-1">
                <div class="col-12 col-md-7 offset-md-3">
                  <div class="form-check">
                    <input type="checkbox" class="form-check-input" id="cpt-private-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][private]" {if $ALBUM.status=='private'}checked{/if} />
                    <label class="form-check-label" for="cpt-private-{$ALBUM.id|escape}">{'Make this gallery private'|@translate}</label>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/foreach}

        <div class="text-right mt-3">
          <button type="button" class="btn btn-primary cpt-save-button">{'Save Changes'|@translate}</button>
        </div>
      {else}
        <p class="text-muted mb-0">{'You have no editable galleries yet.'|@translate}</p>
      {/if}

      <input type="hidden" name="cpt_album_marker" value="1" />
    </div>
  </div>
</div>
