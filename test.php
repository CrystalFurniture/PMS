 <script type="text/javascript" src="js/html2canvas.js"></script> // 1.0.0-alpha.12 downloaded

 <script src="https://cdnjs.cloudflare.com/ajax/libs/jspdf/1.5.3/jspdf.debug.js" integrity="sha384-NaWTHo/8YCBYJ59830LTz/P4aQZK1sS0SneOgAvhsIl3zBu8r9RevNg5lHCHAuQ/" crossorigin="anonymous"></script>

<div id='doc'>
    <p>Hello world</p>
    <div class="first-page">
        <h1>bond</h1>
       
    </div>
    <div class="second-page">
       <h1> Page 2 </h1>
    </div>
</div>
<button onclick="saveDoc()">click</button>


 <script type="text/javascript">
    var pdf = new jsPDF('p', 'pt', 'a4');

    function saveDoc() {
		/* alert("hello");
        window.html2canvas = html2canvas
        const doc = document.getElementById('doc');

        if (doc) {
            console.log("div is ");
            console.log(doc);
            console.log("hellowww");

 */

            pdf.html(document.getElementById('doc'), {
                callback: function (pdf) {
                    pdf.save('DOC.pdf');
                }
            })
       //}
     }
   </script>