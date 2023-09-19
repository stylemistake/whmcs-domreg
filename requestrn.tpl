{if $error}
    <div class="alert alert-error textcenter">
        {$error}
    </div>
{else}
    <div style="text-align: center">
        <p style="font-size: 110%">
            <strong>{$LANG['domaindomregyourregistrantid']}</strong>
        </p>
        <p style="font-size: 200%; background: #EEE; padding: 5px">{$registrant_id}</p>
        <p>
            <a class="btn btn-primary"
                target="_blank"
                href="https://www.domreg.lt/registrant?rnl_login={$registrant_id}">
                {$LANG['domaindomreglogin']}
            </a>
        </p>
    </div>

    <br />
{/if}

<p style="text-align: center"><a href="clientarea.php?action=domaindetails&id={$domainid}">{$LANG['goback']}</a></p>
<br />
