<div class="cpt-album-quick-toggle additional_info" aria-label="{'Album privacy'|@translate}">
  <p><strong>{'Album privacy'|@translate}</strong></p>
  <p>{$CPT_ALBUM_TOGGLE_STATUS_TEXT|escape}</p>
  <form action="{$CPT_ALBUM_TOGGLE_ACTION|escape}" method="post" class="cpt-album-toggle-form">
    <input type="hidden" name="pwg_token" value="{$PWG_TOKEN|default:''|escape}">
    <input type="hidden" name="cpt_album_quick_toggle" value="1">
    <input type="hidden" name="cpt_album_status" value="{$CPT_ALBUM_TOGGLE_TARGET_STATUS|escape}">
    <button type="submit" class="buttonLike cpt-album-toggle-button ui-btn ui-shadow ui-corner-all">
      {if $CPT_ALBUM_IS_PRIVATE}
        {'Change the album to public'|@translate}
      {else}
        {'Change the album to private'|@translate}
      {/if}
    </button>
  </form>
</div>