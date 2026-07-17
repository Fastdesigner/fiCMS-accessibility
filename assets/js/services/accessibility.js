(() => {
	const service = document.currentScript;
	if (!service || window.fiCMSAccessibilityRunning) return;
	window.fiCMSAccessibilityRunning = true;

	(async () => {
		const {AccessibilityAudit} = await import(new URL('../accessibility/accessibility-audit.js',service.src));
		const body = new FormData();
		body.append('accessibility_result',JSON.stringify(await AccessibilityAudit.run({document})));
		await fiCMS__refresh(false,body,false,{params:['loadwidget=settings','settingsType=info-accessibility']});
	})().catch(() => {});
})();
