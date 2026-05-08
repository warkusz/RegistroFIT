(() => {
    // Duração base da animação de troca de páginas.
    const TRANSITION_MS = 260;

    // Filtra links para animar apenas navegação interna normal.
    const isInternalNavigableLink = (anchor) => {
        if (!anchor || !(anchor instanceof HTMLAnchorElement)) return false;
        if (anchor.target && anchor.target.toLowerCase() === "_blank") return false;
        if (anchor.hasAttribute("download")) return false;
        if (anchor.getAttribute("href")?.startsWith("#")) return false;
        if (anchor.getAttribute("href")?.startsWith("mailto:")) return false;
        if (anchor.getAttribute("href")?.startsWith("tel:")) return false;

        const url = new URL(anchor.href, window.location.href);
        if (url.origin !== window.location.origin) return false;
        if (url.href === window.location.href) return false;

        return true;
    };

    // Aplica animação de entrada no carregamento da página.
    const startEnterTransition = () => {
        document.body.classList.add("page-enter");
        requestAnimationFrame(() => {
            document.body.classList.add("page-enter-active");
        });
    };

    // Aplica animação de saída e só depois troca o URL.
    const startLeaveTransition = (nextUrl) => {
        document.body.classList.remove("page-enter-active");
        document.body.classList.add("page-leave");
        window.setTimeout(() => {
            window.location.href = nextUrl;
        }, TRANSITION_MS);
    };

    // Interceta cliques em links internos para suavizar a transição visual.
    document.addEventListener("DOMContentLoaded", () => {
        startEnterTransition();

        document.addEventListener("click", (event) => {
            if (event.defaultPrevented) return;
            if (event.button !== 0) return;
            if (event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) return;

            const anchor = event.target instanceof Element ? event.target.closest("a[href]") : null;
            if (!isInternalNavigableLink(anchor)) return;

            event.preventDefault();
            startLeaveTransition(anchor.href);
        });
    });

    // Corrige estado visual ao voltar com botão "back/forward" do browser.
    window.addEventListener("pageshow", () => {
        document.body.classList.remove("page-leave");
        document.body.classList.add("page-enter", "page-enter-active");
    });
})();
