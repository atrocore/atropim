<div class="plate-item">
	<div class="plate-item-header">
		<div class="field-status" data-name="status">
			{{{productStatusField}}}
		</div>
		<div class="actions">{{{rowActions}}}</div>
	</div>
	<div class="field-preview" data-name="image">
		{{{mainImageField}}}
	</div>
	<div class="field-name">
		<span class="record-checkbox-container">
			<input type="checkbox" class="record-checkbox" data-id="{{model.id}}">
		</span>
		<a href="#{{scope}}/view/{{model.id}}" class="link" data-id="{{model.id}}" title="{{model.attributes.name}}">{{model.attributes.name}}</a>
	</div>
</div>
