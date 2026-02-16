import { registerBlockType } from '@wordpress/blocks';
import { InspectorControls } from '@wordpress/block-editor';
import { PanelBody, ToggleControl, SelectControl, RangeControl, CheckboxControl, TextControl } from '@wordpress/components';
import { __ } from '@wordpress/i18n';
import apiFetch from '@wordpress/api-fetch';
import { Fragment, useEffect, useState } from '@wordpress/element';

registerBlockType('vc-nexudus/products', {
	edit({ attributes, setAttributes, className }) {
		const [products, setProducts] = useState([]);
		const [search, setSearch] = useState('');

		useEffect(() => {
			apiFetch({ path: `/vc-nexudus/v1/products?search=${encodeURIComponent(search)}` })
				.then((items) => setProducts(items || []))
				.catch(() => setProducts([]));
		}, [search]);

		return (
			<Fragment>
				<InspectorControls>
					<PanelBody title={__('Product Selection', 'vc-nexudus')} initialOpen>
						<TextControl label={__('Search Products', 'vc-nexudus')} value={search} onChange={setSearch} />
						{products.map((item) => (
							<CheckboxControl
								key={item.id}
								label={`${item.name} (${item.type})`}
								checked={(attributes.ids || []).includes(item.id)}
								onChange={(checked) => {
									const ids = [...(attributes.ids || [])];
									if (checked && !ids.includes(item.id)) ids.push(item.id);
									if (!checked) setAttributes({ ids: ids.filter((id) => id !== item.id) });
									else setAttributes({ ids });
								}}
							/>
						))}
						<SelectControl
							label={__('Layout', 'vc-nexudus')}
							value={attributes.layout || 'grid'}
							options={[{ label: 'Grid', value: 'grid' }, { label: 'List', value: 'list' }]}
							onChange={(layout) => setAttributes({ layout })}
						/>
						<RangeControl label={__('Columns', 'vc-nexudus')} value={attributes.columns || 3} min={1} max={4} onChange={(columns) => setAttributes({ columns })} />
						<ToggleControl label={__('Show Price', 'vc-nexudus')} checked={attributes.showPrice !== false} onChange={(showPrice) => setAttributes({ showPrice })} />
						<ToggleControl label={__('Show Description', 'vc-nexudus')} checked={!!attributes.showDescription} onChange={(showDescription) => setAttributes({ showDescription })} />
					</PanelBody>
				</InspectorControls>
				<div className={className}>{__('VC Nexudus products block (server-rendered preview on frontend).', 'vc-nexudus')}</div>
			</Fragment>
		);
	},
	save() {
		return null;
	},
});
