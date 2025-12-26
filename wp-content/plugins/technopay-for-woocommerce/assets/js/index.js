const settings = window.wc.wcSettings.getSetting('technopay_data', {});
const label = window.wp.htmlEntities.decodeEntities(settings.title) || window.wp.i18n.__('تکنوپی', 'technopay-for-woocommerce');
const icon = settings.icon || '';

const Content = () => {
    return window.wp.htmlEntities.decodeEntities(settings.description || '');
};

const Label = (props) => {
    const { PaymentMethodLabel } = props.components;
    const { createElement } = window.wp.element;
    
    return createElement(
        'span',
        { style: { display: 'flex', alignItems: 'center', gap: '8px' } },
        createElement(PaymentMethodLabel, { text: label }),
        icon && createElement('img', {
            src: icon,
            alt: label,
            style: { height: '24px', width: 'auto', marginLeft: '8px' }
        })
    );
};

const TechnoPayPaymentMethod = {
    name: 'technopay',
    label: window.wp.element.createElement(Label, null),
    content: window.wp.element.createElement(Content, null),
    edit: window.wp.element.createElement(Content, null),
    canMakePayment: () => true,
    ariaLabel: label,
    supports: {
        features: settings.supports || [],
    },
};

window.wc.wcBlocksRegistry.registerPaymentMethod(TechnoPayPaymentMethod);
