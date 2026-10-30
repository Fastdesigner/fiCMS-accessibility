(() => {
	const service = document.currentScript;
	if (!service || window.fiCMSAccessibilityRunning || navigator.webdriver || fiCMS.vars.global.refresh_allowed === 0) return;
	window.fiCMSAccessibilityRunning = true;

	(async () => {
		const {AccessibilityAudit} = await import(new URL('../accessibility/accessibility-audit.js',service.src));
		const body = new FormData();
		body.append('accessibility_result',JSON.stringify(await AccessibilityAudit.run({document})));
		await fiCMS__refresh(false,body,false,{params:['loadwidget=settings','settingsType=info-accessibility']});
	})().catch(() => {});
})();
