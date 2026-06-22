{* Album manager partial: injected into profile form by JS. No <form> tag here. *}
<div class="cpt-album-manager" aria-describedby="cpt-album-manager-help">
  <div class="card mb-3">
    <h4 class="card-header">{'My Galleries'|@translate}</h4>
    <div class="card-body">
      <p id="cpt-album-manager-help" class="cpt-help text-muted small mb-3">{'Edit your galleries and save them here.'|@translate}</p>
      {if !empty($CPT_LIMITED_MODE_NOTICE)}
        <div class="alert alert-info cpt-limited-mode-notice" role="status">{$CPT_LIMITED_MODE_NOTICE|escape}</div>
      {/if}
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
                <label for="cpt-visibility-{$ALBUM.id|escape}" class="col-12 col-md-3 col-form-label">{'Visibility'|@translate}</label>
                <div class="col-12 col-md-7">
                  <select class="form-control cpt-visibility-select" id="cpt-visibility-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][visibility]">
                    <option value="public" {if $ALBUM.visibility=='public'}selected{/if}>{'Public'|@translate}</option>
                    <option value="private" {if $ALBUM.visibility=='private'}selected{/if}>{'Private'|@translate}</option>
                    <option value="shared" {if $ALBUM.visibility=='shared'}selected{/if}>{'Shared with selected users'|@translate}</option>
                  </select>
                </div>
              </div>
              <div class="form-group row mb-1 cpt-shared-users-group" {if $ALBUM.visibility!='shared'}hidden{/if}>
                <label for="cpt-shared-users-{$ALBUM.id|escape}" class="col-12 col-md-3 col-form-label">{'People with access'|@translate}</label>
                <div class="col-12 col-md-7">
                  {if isset($CPT_SHAREABLE_USERS) && $CPT_SHAREABLE_USERS|@count > 0}
                    <select class="form-control cpt-shared-users-select" id="cpt-shared-users-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][shared_users][]" multiple="multiple" size="5">
                      {foreach from=$CPT_SHAREABLE_USERS key=SHARED_USER_ID item=SHARED_USERNAME}
                        <option value="{$SHARED_USER_ID|escape}" {if isset($ALBUM.shared_user_lookup[$SHARED_USER_ID])}selected{/if}>{$SHARED_USERNAME|escape}</option>
                      {/foreach}
                    </select>
                    <small class="form-text text-muted">{'Select one or more users to keep this gallery shared.'|@translate}</small>
                  {else}
                    <p class="form-text text-muted mb-0">{'No other users are available for sharing.'|@translate}</p>
                  {/if}
                </div>
              </div>
              <div class="form-group row mb-1 cpt-cover-image-group">
                <label class="col-12 col-md-3 col-form-label" for="cpt-representative-picture-{$ALBUM.id|escape}">{'Cover image'|@translate}</label>
                <div class="col-12 col-md-7">
                  <input type="hidden" class="cpt-representative-input" id="cpt-representative-picture-{$ALBUM.id|escape}" name="cpt_album[{$ALBUM.id|escape}][representative_picture_id]" value="{$ALBUM.representative_picture_id|default:''|escape}" />
                  <div class="cpt-representative-current d-flex align-items-center gap-2 mb-2" data-empty-label="{'No cover image selected.'|@translate|escape}" data-missing-label="{'Current cover image is unavailable.'|@translate|escape}">
                    {if !empty($ALBUM.representative_src)}
                      <img src="{$ALBUM.representative_src|escape}" alt="{$ALBUM.representative_label|escape}" class="cpt-representative-thumb" loading="lazy" />
                    {/if}
                    <span class="cpt-representative-label">{$ALBUM.representative_label|default:{'No cover image selected.'|@translate}|escape}</span>
                  </div>
                  <div class="d-flex flex-wrap gap-2 align-items-center mb-2">
                    <button type="button" class="btn btn-outline-secondary btn-sm cpt-load-representatives">{'Choose cover image'|@translate}</button>
                    <button type="button" class="btn btn-link btn-sm cpt-clear-representative" {if empty($ALBUM.representative_picture_id)}hidden{/if}>{'Clear cover image'|@translate}</button>
                  </div>
                  <div class="cpt-representative-picker" hidden>
                    <div class="cpt-representative-options row g-2"></div>
                    <p class="cpt-representative-empty text-muted small mb-0" hidden>{'This gallery has no photos available for a cover image yet.'|@translate}</p>
                  </div>
                </div>
              </div>
            </div>
          </div>
        {/foreach}

        <div class="text-right mt-3 cpt-save-actions">
          <button type="button" class="btn btn-primary cpt-save-button">{'Save Changes'|@translate}</button>
        </div>
      {/if}
      {if !isset($UCP_ALBUMS) || $UCP_ALBUMS|@count == 0}
        <p class="text-muted mb-0">{'You have no editable galleries yet.'|@translate}</p>
      {/if}

      <input type="hidden" name="cpt_album_marker" value="1" />
    </div>
  </div>
</div>
