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
if(!configuration.hasOwnProperty('endpoint')) {
	console.error(new Error('`endpoint` field'));
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
(async audits => {
	for(let i = 0; i < audits.length; i++) {
		const params = new URLSearchParams();
		params.append('input_type', 'url_input');
		params.append('url', configuration.url_prefix + '/' + audits[i]);
		try {
			let r = await axios({
				method: 'post',
				url: configuration.endpoint,
				validateStatus: s => s >= 200,
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded',
				},
				data: params,
			});
			if(r.status === 200) {
				console.log((i + 1) + '/' + audits.length);
			} else {
				console.log((i + 1) + '!');
			}
		} catch (e) {
			console.error(e);
			return;
		}
		await new Promise(_ => setTimeout(_, configuration.delay));
	}
})(configuration.audits);