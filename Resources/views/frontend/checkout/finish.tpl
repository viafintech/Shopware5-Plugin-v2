{extends file='parent:frontend/checkout/finish.tpl'}
{block name='frontend_checkout_finish_teaser'}
    {$smarty.block.parent}
    {if $smarty.session.Shopware.checkout_token != ""}
        <script src="https://cdn.barzahlen.de/js/v2/checkout{$smarty.session.Shopware.sandbox}.js" class="bz-checkout" data-token="{$smarty.session.Shopware.checkout_token}"></script>
    {/if}
    {$smarty.session.Shopware.checkout_token = ""}
{/block}
