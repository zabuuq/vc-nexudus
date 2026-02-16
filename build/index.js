(function (blocks, element, i18n, components, blockEditor, apiFetch) {
	var el = element.createElement;
	var __ = i18n.__;
	var InspectorControls = blockEditor.InspectorControls;
	var PanelBody = components.PanelBody;
	var ToggleControl = components.ToggleControl;
	var SelectControl = components.SelectControl;
	var RangeControl = components.RangeControl;
	var CheckboxControl = components.CheckboxControl;
	var TextControl = components.TextControl;

	blocks.registerBlockType('vc-nexudus/products', {
		edit: function (props) {
			var attributes = props.attributes;
			var setAttributes = props.setAttributes;
			var products = element.useState([]);
			var search = element.useState('');
			var productList = products[0];
			var setProductList = products[1];
			var searchValue = search[0];
			var setSearchValue = search[1];

			element.useEffect(function () {
				apiFetch({ path: '/vc-nexudus/v1/products?search=' + encodeURIComponent(searchValue) }).then(function (items) {
					setProductList(items || []);
				}).catch(function () {
					setProductList([]);
				});
			}, [searchValue]);

			return el(
				element.Fragment,
				null,
				el(InspectorControls, null,
					el(PanelBody, { title: __('Product Selection', 'vc-nexudus'), initialOpen: true },
						el(TextControl, {
							label: __('Search Products', 'vc-nexudus'),
							value: searchValue,
							onChange: setSearchValue
						}),
						productList.map(function (item) {
							return el(CheckboxControl, {
								key: item.id,
								label: item.name + ' (' + item.type + ')',
								checked: (attributes.ids || []).indexOf(item.id) !== -1,
								onChange: function (checked) {
									var ids = attributes.ids ? attributes.ids.slice() : [];
									if (checked && ids.indexOf(item.id) === -1) {
										ids.push(item.id);
									}
									if (!checked) {
										ids = ids.filter(function (id) { return id !== item.id; });
									}
									setAttributes({ ids: ids });
								}
							});
						}),
						el(SelectControl, {
							label: __('Layout', 'vc-nexudus'),
							value: attributes.layout || 'grid',
							options: [{ label: 'Grid', value: 'grid' }, { label: 'List', value: 'list' }],
							onChange: function (value) { setAttributes({ layout: value }); }
						}),
						el(RangeControl, {
							label: __('Columns', 'vc-nexudus'),
							value: attributes.columns || 3,
							min: 1,
							max: 4,
							onChange: function (value) { setAttributes({ columns: value }); }
						}),
						el(ToggleControl, {
							label: __('Show Price', 'vc-nexudus'),
							checked: attributes.showPrice !== false,
							onChange: function (value) { setAttributes({ showPrice: value }); }
						}),
						el(ToggleControl, {
							label: __('Show Description', 'vc-nexudus'),
							checked: !!attributes.showDescription,
							onChange: function (value) { setAttributes({ showDescription: value }); }
						})
					)
				),
				el('div', { className: props.className }, __('VC Nexudus products block (server-rendered preview on frontend).', 'vc-nexudus'))
			);
		},
		save: function () {
			return null;
		}
	});
})(window.wp.blocks, window.wp.element, window.wp.i18n, window.wp.components, window.wp.blockEditor, window.wp.apiFetch);
