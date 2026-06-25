{if isset($UCP_OWNER_PROFILE) && !empty($UCP_OWNER_PROFILE.fields)}
  <div class="cpt-owner-profile" data-root-album-id="{$UCP_OWNER_PROFILE.root_album_id|escape}">
    <p class="cpt-help text-muted small mb-3">{'These details may be displayed publicly on your main gallery page.'|@translate}</p>
    <div class="cpt-status alert" role="status" aria-live="polite" hidden></div>

    {foreach from=$UCP_OWNER_PROFILE.fields item=PROFILE_FIELD}
      {if $PROFILE_FIELD.key == 'contact_number'}
        <hr class="my-4" />
        <h5 class="mb-3">{'Contact'|@translate}</h5>
      {elseif $PROFILE_FIELD.key == 'availability_monday'}
        <hr class="my-4" />
        <h5 class="mb-3">{'Availability'|@translate}</h5>
      {/if}
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
          {elseif $PROFILE_FIELD.type == 'controlled_multi'}
            <select class="form-control" id="cpt-owner-profile-{$PROFILE_FIELD.key|escape}" name="cpt_owner_profile[{$PROFILE_FIELD.key|escape}][tag_ids][]" multiple>
              {foreach from=$PROFILE_FIELD.options key=OPTION_ID item=OPTION_LABEL}
                <option value="{$OPTION_ID|escape}" {if in_array($OPTION_ID, $PROFILE_FIELD.selected_tag_ids)}selected{/if}>{$OPTION_LABEL|escape}</option>
              {/foreach}
            </select>
          {elseif $PROFILE_FIELD.type == 'availability_range'}
            <div class="row g-2">
              <div class="col-6">
                <select class="form-control" id="cpt-owner-profile-{$PROFILE_FIELD.key|escape}-from" data-role="from" name="cpt_owner_profile[{$PROFILE_FIELD.key|escape}][from_value]">
                  <option value="">{'From'|@translate}</option>
                  {foreach from=$PROFILE_FIELD.options key=OPTION_VALUE item=OPTION_LABEL}
                    <option value="{$OPTION_VALUE|escape}" {if $PROFILE_FIELD.from_value == $OPTION_VALUE}selected{/if}>{$OPTION_LABEL|escape}</option>
                  {/foreach}
                </select>
              </div>
              <div class="col-6">
                <select class="form-control" id="cpt-owner-profile-{$PROFILE_FIELD.key|escape}-to" data-role="to" name="cpt_owner_profile[{$PROFILE_FIELD.key|escape}][to_value]">
                  <option value="">{'To'|@translate}</option>
                  {foreach from=$PROFILE_FIELD.options key=OPTION_VALUE item=OPTION_LABEL}
                    {if $OPTION_VALUE != 'unavailable'}
                      <option value="{$OPTION_VALUE|escape}" {if $PROFILE_FIELD.to_value == $OPTION_VALUE}selected{/if}>{$OPTION_LABEL|escape}</option>
                    {/if}
                  {/foreach}
                </select>
              </div>
            </div>
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