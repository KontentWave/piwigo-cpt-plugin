<div class="cpt-owner-profile-public">
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
</div>