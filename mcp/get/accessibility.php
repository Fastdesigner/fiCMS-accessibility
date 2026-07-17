<?php

if (!$site['onsite']) return false;

if ($mcp['mode'] === 'capabilities') return (($mcp['scope'] ?? 'user') === 'admin') ? [
	'tool'=>'get',
	'type'=>'accessibility',
	'text'=>'use get("accessibility","summary") for the current automated audit overview, get("accessibility","pages") for audited page and viewport coverage, get("accessibility","page:<mid-tid-lid>") for concrete findings, and get("skill","accessibility") before making accessibility claims'
] : false;

if (($mcp['scope'] ?? 'user') !== 'admin') return ['error'=>'admin scope required.'];
if (!isset($tables[\accessibility\Config::TABLE]) || !\accessibility\License::allowed()) return ['error'=>'accessibility audits are not available.'];

return \accessibility\McpView::read(trim(strval($get['id'])),mcp__task_language($mcp['task']) ?: (string) ($user['language'] ?? $site['default_language'] ?? 'en'));
