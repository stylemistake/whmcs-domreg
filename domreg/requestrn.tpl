<div class="alert alert-block alert-info">
    <p>{$LANG.domainname}: <strong>{$domain}</strong></p>
</div>

{if $error}
<div class="alert alert-error textcenter">
    {$error}
</div>
{else}
<p style="font-size: 110%; text-align: center">
	<b>Your client id is:</b><br />
</p>
<p style="font-size: 200%; text-align: center; background: #EEE; padding: 5px">{$registrant_id}</p>

<br />
{/if}

<p style="text-align: center"><a href="clientarea.php?action=domaindetails&id={$domainid}">Go back</a></p>
<br />