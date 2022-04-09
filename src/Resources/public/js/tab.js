window.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.js--tabs').forEach(function (panelContainer) {
        let panelLinks = panelContainer.querySelectorAll('[data-tab-target]');

        panelLinks.forEach(function (panelLink) {
            panelLink.addEventListener('click', function () {
                panelLinks.forEach(function (link) {
                    let activeTarget = panelContainer.querySelector('[data-tab-active="' + link.dataset.tabTarget + '"]') || link;
                    activeTarget.classList.toggle('is-active', link === panelLink);
                });

                panelContainer.querySelectorAll('[data-tab]')
                    .forEach(t => t.classList.toggle('is-hidden', t.dataset.tab !== panelLink.dataset.tabTarget));
            });
        })
    });
});