<div class="field" data-name="valueField">
    {{#if useTextarea}}
    <textarea data-name="valueField1" class="field main-element form-control auto-height" disabled="disabled" rows="4">
        {{{valueField}}}
    </textarea>
    {{else}}
        {{{valueField}}}
    {{/if}}
</div>
