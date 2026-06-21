{if isset($UCP_OWNER_PROFILE) && !empty($UCP_OWNER_PROFILE.fields)}
  <div class="cpt-owner-profile" data-root-album-id="{$UCP_OWNER_PROFILE.root_album_id|escape}">
    <p class="cpt-help text-muted small mb-3">{'These details may be displayed publicly on your main gallery page.'|@translate}</p>
    <div class="cpt-status alert" role="status" aria-live="polite" hidden></div>

    {foreach from=$UCP_OWNER_PROFILE.fields item=PROFILE_FIELD}
      <div class="form-group row align-items-center cpt-owner-profile-field" data-field-key="{$PROFILE_FIELD.key|escape}" data-field-type="{$PROFILE_FIELD.type|escape}">
        <label class="col-12 col-md-3 col-form-label" for="cpt-owner-profile-{$PROFILE_FIELD.key|escape}">{$PROFILE_FIELD.label|escape}</label>
        <div class="col-12 col-md-7">
          {if $PROFILE_FIELD.type == 'controlled'}
            <select class="form-control" id="cpt-owner-profile-{$PROFILE_FIELD.key|escape}" name="cpt_owner_profile[{$PROFILE_FIELD.key|escape}][tag_id]">
              <option value="">-</option>
              {foreach from=$PROFILE_FIELD.options key=OPTION_ID item=OPTION_LABEL}
                <option value="{$OPTION_ID|escape}" {if $PROFILE_FIELD.tag_id == $OPTION_ID}selected{/if}>{$OPTION_LABEL|escape}</option>
              {/foreach}
            </select>
          {else}
            <input type="text" class="form-control" id="cpt-owner-profile-{$PROFILE_FIELD.key|escape}" name="cpt_owner_profile[{$PROFILE_FIELD.key|escape}][value_text]" value="{$PROFILE_FIELD.value_text|escape}" {if !empty($PROFILE_FIELD.max_length)}maxlength="{$PROFILE_FIELD.max_length|escape}"{/if} />
          {/if}
        </div>
      </div>
    {/foreach}

    <div class="text-right mt-3 cpt-owner-profile-save-actions">
      <button type="button" class="btn btn-primary cpt-owner-profile-save-button">{'Save Public Profile'|@translate}</button>
    </div>
  </div>
{/if}