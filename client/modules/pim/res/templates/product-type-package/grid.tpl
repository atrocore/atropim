{{#each layoutFields}}
<div class="cell col-sm-6 form-group" data-name="{{this}}">
    <label class="control-label" data-name="{{this}}">
        <span class="label-text">{{translate this scope="ProductTypePackage" category="fields"}}</span>
    </label>
    <div class="field" data-name="{{this}}"></div>
</div>
{{/each}}