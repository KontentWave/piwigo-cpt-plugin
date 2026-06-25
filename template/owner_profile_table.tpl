<div class="cpt-owner-profile-public">
  {if !empty($CPT_OWNER_PROFILE_ROWS)}
    <table class="cpt-owner-profile-table">
      <tbody>
        {foreach from=$CPT_OWNER_PROFILE_ROWS item=PROFILE_ROW}
          <tr>
            <th scope="row">{$PROFILE_ROW.label|escape}</th>
            <td>{$PROFILE_ROW.value_text|escape}</td>
          </tr>
        {/foreach}
      </tbody>
    </table>
  {/if}

  {if !empty($CPT_OWNER_PROFILE_CONTACTS)}
    <div class="cpt-owner-profile-contacts mt-4">
      <h5 class="mb-3">{'Contact'|@translate}</h5>
      <div class="cpt-owner-profile-contact-actions mb-3">
        {foreach from=$CPT_OWNER_PROFILE_CONTACTS item=CONTACT}
          <a class="cpt-owner-profile-contact-link cpt-owner-profile-contact-{$CONTACT.key|escape}" href="{$CONTACT.href|escape}" rel="nofollow noopener" target="_blank" aria-label="{$CONTACT.label|escape}: {$CONTACT.display_value|escape}" title="{$CONTACT.label|escape}">
            <span class="cpt-owner-profile-contact-icon" aria-hidden="true">
              {if $CONTACT.key == 'phone'}
                <svg viewBox="0 0 24 24" focusable="false">
                  <path d="M6.62 10.79c1.44 2.83 3.76 5.15 6.59 6.59l2.2-2.2a1 1 0 0 1 1.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 0 1 1 1V20a1 1 0 0 1-1 1C10.07 21 3 13.93 3 5a1 1 0 0 1 1-1h3.49a1 1 0 0 1 1 1c0 1.24.2 2.45.57 3.57a1 1 0 0 1-.24 1.02l-2.2 2.2Z" fill="currentColor"/>
                </svg>
              {elseif $CONTACT.key == 'sms'}
                <svg viewBox="0 0 24 24" focusable="false">
                  <path d="M4 5h16a2 2 0 0 1 2 2v8a2 2 0 0 1-2 2H9l-5 4v-4H4a2 2 0 0 1-2-2V7a2 2 0 0 1 2-2Zm2 4v2h12V9H6Zm0 4v2h8v-2H6Z" fill="currentColor"/>
                </svg>
              {elseif $CONTACT.key == 'whatsapp'}
                <svg viewBox="0 0 24 24" focusable="false">
                  <path d="M12 2a10 10 0 0 0-8.76 14.82L2 22l5.33-1.18A10 10 0 1 0 12 2Zm0 18a8 8 0 0 1-4.08-1.12l-.29-.17-3.16.7.68-3.08-.19-.31A8 8 0 1 1 12 20Zm4.39-5.57c-.24-.12-1.43-.7-1.65-.78-.22-.08-.38-.12-.54.12s-.62.78-.76.94c-.14.16-.28.18-.52.06a6.54 6.54 0 0 1-1.92-1.18 7.18 7.18 0 0 1-1.33-1.65c-.14-.24-.01-.37.1-.49.11-.11.24-.28.36-.42.12-.14.16-.24.24-.4.08-.16.04-.3-.02-.42-.06-.12-.54-1.3-.74-1.78-.2-.48-.4-.41-.54-.41h-.46a.88.88 0 0 0-.64.3c-.22.24-.84.82-.84 1.99 0 1.17.86 2.3.98 2.46.12.16 1.69 2.58 4.09 3.62.57.24 1.02.38 1.37.49.58.18 1.11.15 1.53.09.47-.07 1.43-.58 1.63-1.13.2-.56.2-1.03.14-1.13-.06-.1-.22-.16-.46-.28Z" fill="currentColor"/>
                </svg>
              {/if}
            </span>
          </a>
        {/foreach}
      </div>
      <div class="cpt-owner-profile-contact-number">{$CPT_OWNER_PROFILE_CONTACTS[0].display_value|escape}</div>
    </div>
  {/if}

  {if !empty($CPT_OWNER_PROFILE_AVAILABILITY)}
    <div class="cpt-owner-profile-availability mt-4">
      <h5 class="mb-3">{'Availability'|@translate}</h5>
      <div class="cpt-owner-profile-availability-list">
        {foreach from=$CPT_OWNER_PROFILE_AVAILABILITY item=AVAILABILITY_ROW}
          <div class="cpt-owner-profile-availability-row">
            <span class="cpt-owner-profile-availability-day">{$AVAILABILITY_ROW.label|escape}</span>
            <span class="cpt-owner-profile-availability-time">{$AVAILABILITY_ROW.value_text|escape}</span>
          </div>
        {/foreach}
      </div>
    </div>
  {/if}
</div>