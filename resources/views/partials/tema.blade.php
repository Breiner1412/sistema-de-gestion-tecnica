{{--
    Aplica el tema antes de que el navegador pinte nada, para que no parpadee.
    El servidor ya pone class="dark" cuando la preferencia es "oscuro"; esto
    resuelve "sistema", que solo el navegador sabe. Va en el <head>, antes de
    los estilos.
--}}
<script>
    (function () {
        var html = document.documentElement;
        var tema = html.getAttribute('data-tema');
        var oscuro = tema === 'oscuro' || (tema === 'sistema' && window.matchMedia('(prefers-color-scheme: dark)').matches);
        html.classList.toggle('dark', oscuro);
    })();
</script>
