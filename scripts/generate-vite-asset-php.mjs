import { writeFileSync } from 'node:fs';
import { resolve } from 'node:path';

const assetPath = resolve(process.cwd(), 'build/admin.asset.php');

const contents = `<?php return array(
    'dependencies' => array('wp-element', 'wp-components', 'wp-i18n', 'wp-api-fetch', 'wp-data'),
    'version' => '${Date.now().toString(16)}',
);
`;

writeFileSync(assetPath, contents, 'utf8');
