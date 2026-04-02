import { __ } from '@wordpress/i18n';
import { Card, CardBody, CardHeader } from '@wordpress/components';

export default function Dashboard() {
    return (
        <div className="spolszczony-dashboard">
            <Card>
                <CardHeader>
                    <h2>{__('Compliance Dashboard', 'spolszczony')}</h2>
                </CardHeader>
                <CardBody>
                    <p>
                        {__(
                            'Welcome to Spolszczony — Polish e-commerce compliance for WooCommerce.',
                            'spolszczony',
                        )}
                    </p>
                </CardBody>
            </Card>
        </div>
    );
}
