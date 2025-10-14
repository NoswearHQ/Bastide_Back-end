import 'swagger-ui-dist/swagger-ui.css';
import SwaggerUI from 'swagger-ui-dist/swagger-ui-es-bundle.js';

SwaggerUI({
    dom_id: '#swagger-ui',
    url: '/api/docs.yaml', // <-- utiliser YAML
});
