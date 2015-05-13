{* template block that contains the new field *}
  {*<div>Attach File:{$form.uploadFile.html}{$form.attachDesc.html} </div>*}
  <div class="crm-msg-template-form-block-attachment">
  {if $action eq 1 || $action eq 2}
     {include file="CRM/Form/attachment.tpl"}
 
 {if $defaults}
    <table>
      <tr>
        <td class="label">{ts}Current Attachment(s){/ts}</td>
            <td class="view-value">
          {foreach from=$defaults key=attKey item=attVal}
                <div class="crm-attachment-wrapper crm-entity" id="file_{$attVal.fileID}">
                  <strong><a class="crm-attachment" href="{$attVal.url}">{$attVal.cleanName}</a></strong>
                  {if $attVal.description}&nbsp;-&nbsp;{$attVal.description}{/if}
                  {if $attVal.deleteURLArgs}
                   <a href="#" class="crm-hover-button delete-attachment" data-filename="{$attVal.cleanName}" data-args="{$attVal.deleteURLArgs}" title="{ts}Delete File{/ts}"><span class="icon delete-icon"></span></a>
                  {/if}
                  {if !empty($attVal.tag)}
                    <br/>
                    {ts}Tags{/ts}: {$attVal.tag}
                    <br/>
                  {/if}
                </div>
          {/foreach}
            </td>
        </tr>
        {if $defaults}
          {include file="CRM/Form/attachmentjs.tpl"}
        {/if}
        </table>
  {/if}
  {/if}
 </div>
{* reposition the above block after #someOtherBlock *}
{literal}
<script type="text/javascript">
  cj('.crm-msg-template-form-block-attachment').insertAfter(cj("#pdf_format_id").parent().parent().parent());
  cj('.crm-msg-template-form-block-attachment').find('.crm-accordion-header').text('Attachment( for WordMailMerge)');
  cj(document).ready(function(){
    cj('.crm-msg-template-form-block-attachment').find('.crm-accordion-wrapper').removeClass();
    cj('.crm-msg-template-form-block-attachment').children().attr('class', 'crm-accordion-wrapper');
    cj('.crm-msg-template-form-block-attachment').find('.crm-accordion-body').show();
  });
   CRM.$(function($) {
        var $form = $("form.{/literal}{$form.formClass}{literal}");
        $form
          .on('click', '.crm-clear-attachment', function(e) {
            e.preventDefault();
            $(this).css('visibility', 'hidden').closest('td').find(':input').val('');
          })
          .on('change', '#attachments :input', function() {
            $(this).closest('td').find('.crm-clear-attachment').css('visibility', 'visible');
          });
      });
</script>
{/literal}

<script type="text/javascript">
{literal}
  CRM.$(function($) {
    $('a.delete-attachment').off('.crmAttachments').on('click.crmAttachments', function(e) {
      var $el = $(this),
        $row = $el.closest('.crm-attachment-wrapper'),
        msg = '{/literal}{ts escape="js" 1="%1"}This will immediately delete the file  %1. This action cannot be undone.{/ts}{literal}';
      CRM.confirm({
        title: $el.attr('title'),
        message: ts(msg, {1: '<em>' + $el.data('filename') + '</em>'})
      }).on('crmConfirm:yes', function() {
        var postUrl = {/literal}"{crmURL p='civicrm/ajax/rest' h=0 q='className=CRM_Wordmailmerge_Page_AJAX&fnName=msgTemplateIdAjax&json=1'}"{literal};
        var request = $.post(postUrl, $el.data('args'));
        CRM.status({success: '{/literal}{ts escape="js"}Removed{/ts}{literal}'}, request);
        request.done(function() {
          $el.trigger('crmPopupFormSuccess');
          $row.remove();
        });
      });
      e.preventDefault();
    });
  });
{/literal}
</script>
