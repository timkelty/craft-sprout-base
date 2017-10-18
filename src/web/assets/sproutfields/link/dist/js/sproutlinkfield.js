/*
 * @link      https://sprout.barrelstrengthdesign.com/
 * @copyright Copyright (c) Barrel Strength Design LLC
 * @license   http://sprout.barrelstrengthdesign.com/license
 */

function checkSproutLinkField(namespaceInputId, id, fieldHandle, fieldContext) {

	var sproutLinkFieldId = '#' + namespaceInputId;
	var sproutLinkButtonClass = '.' + id;

	// We use setTimeout to make sure our function works every time
	setTimeout(function()
	{
		// Set up data for the controller.
		var data = {
			'fieldHandle': fieldHandle,
			'fieldContext': fieldContext,
			'value': $(sproutLinkFieldId).val()
		};

		// Query the controller so the regex validation is all done through PHP.
		Craft.postActionRequest('sprout-core/fields/link-validate', data, function(response) {
			if (response)
			{
				$(sproutLinkButtonClass).addClass('fade');
				$(sproutLinkButtonClass).html('<a href="' + data.value + '" target="_blank" class="sproutfields-icon">&#xf0a9;</a>');
			}
			else
			{
				$(sproutLinkButtonClass).removeClass('fade');
			}
		});

	}, 500);
}