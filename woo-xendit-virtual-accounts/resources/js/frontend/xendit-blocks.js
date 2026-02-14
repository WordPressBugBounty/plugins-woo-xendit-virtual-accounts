
import { __ } from '@wordpress/i18n';
import { registerPaymentMethod } from '@woocommerce/blocks-registry';
import parse from "html-react-parser";

const { availableGateways, isLive } = xenditBlockData.gatewayData

const defaultLabel = __(
    'Xendit Payment',
    'woo-xendit-virtual-accounts',
);

const label = defaultLabel;

/**
 * Content component
 * @param props
 * @returns {JSX.Element}
 * @constructor
 */
const Content = ( props ) => {
    const { description, isLive } = props;
    const className = !isLive ? 'test-description' : '';
    const splitDescription = description.split('|');
    return (
        <>
            <div className='xendit-gateway-payment-description'>{ parse(splitDescription[0]) }</div>
            { !isLive && <TestContent description={splitDescription[1]} className={className} /> }
        </>
    );
};

/**
 * Content component
 * @param props
 * @returns {JSX.Element}
 * @constructor
 */
const TestContent = (props) => {
    const { description, className } = props;
    return (
        <div className={className}>{ parse(description) }</div>
    );
};

/**
 * Label component
 * @param props
 * @returns {JSX.Element}
 * @constructor
 */
const Label = ( props ) => {
    const { title } = props;
    return parse(title);
}

/**
 * Xendit payment method config object.
 * @param item
 * @returns {{edit: JSX.Element, name, supports: {features}, label: JSX.Element, canMakePayment: (function(): boolean), content: JSX.Element, ariaLabel: string}}
 * @constructor
 */
const XenditGateway = (item) => {
    return {
        name: item.id,
        label: <Label title={item.title} />,
        content: <Content description={item.description} isLive={isLive} />,
        edit: <Content description={item.description} isLive={isLive} />,
        canMakePayment: () => true,
        ariaLabel: label,
        supports: {
            features: item.supports,
        },
    };
};

// Register Xendit payment channel
availableGateways.forEach(item => {
    let register = () => registerPaymentMethod(XenditGateway(item));
    register();
});
