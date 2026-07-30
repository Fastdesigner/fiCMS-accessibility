<?php

if (!$site['onsite']) return false;

if ($context->mode === 'describe') return [
	'purpose'=>'Loads stored automated accessibility audit evidence. Use summary for the overview, pages for audited coverage, page:<mid-tid-lid> for concrete findings, and read the accessibility skill before making accessibility claims.',
	'args'=>['id'=>'"summary", "pages", or "page:<mid-tid-lid>".'],
	'scope'=>['admin']
];

if (!\accessibility\Repository::available() || !\accessibility\License::allowed()) return ['error'=>'accessibility audits are not available.'];

return \accessibility\McpView::read(trim(strval($context->args['id'] ?? '')),\mcp\Util::language($context->task) ?: (string) ($user['language'] ?? $site['default_language'] ?? 'en'));
