'use strict';
const axios = require('axios');
const fs = require('fs');
const path = require('path');
const yaml = require('js-yaml');
const confPath = './audits.yaml';
let configuration = {};
try {
  configuration = yaml.load(fs.readFileSync(confPath, 'utf8'));
} catch (e) {
  console.error(e);
  return 1;
}
if(!configuration.hasOwnProperty('dir')) {
	console.error(new Error('`dir` field'));
	return 1;
}
if(!configuration.hasOwnProperty('audits') && Array.isArray(configuration.audits)) {
	console.error(new Error('`audits` field'));
	return 1;
}
configuration.audits = configuration.audits.map(audit => audit.trim());
if(!configuration.hasOwnProperty('url_prefix')) {
	console.error(new Error('`url_prefix` field'));
	return 1;
}
configuration.delay =
	configuration.hasOwnProperty('delay') ?
		configuration.delay :
		0;
if(!fs.existsSync(configuration.dir)) {
	console.error(new Error('Ошибка при доступе к папке ' + configuration.dir));
	return 1;
}
let audits = fs.readdirSync(configuration.dir).filter(audit => (
	path.extname(audit) === '.xml' && configuration.audits.includes(audit)
));	
(async audits => {
	for(let i = 0; i < audits.length; i++) {
		const params = new URLSearchParams();
		params.append('input_type', 'url_input');
		params.append('url', configuration.url_prefix + '/' + audits[i]);
		try {
			let r = await axios({
				method: 'POST',
				url: configuration.endpoint,
				validateStatus: s => s >= 200,
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				data: params,
			});
			if(r.status === 200) {
				console.log(i + '/' + audits.length);
			} else {
				console.log('!');
			}
		} catch (e) {
			console.error(e);
			return 1;
		}
		await new Promise(_ => setTimeout(_, configuration.delay));
	}
})(audits);