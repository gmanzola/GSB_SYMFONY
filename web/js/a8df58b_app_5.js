$(document).ready(function () {
    $('#TableFraisHF').DataTable({
        language: {
            processing: "Traitement en cours...",
            search: "Rechercher&nbsp;:",
            loadingRecords: "Chargement en cours...",
            zeroRecords: "Aucun &eacute;l&eacute;ment &agrave; afficher",
            emptyTable: "Aucune donnée disponible dans le tableau",
            paginate: {
                first: "Premier",
                previous: "Pr&eacute;c&eacute;dent",
                next: "Suivant",
                last: "Dernier"
            },
            aria: {
                sortAscending: ": activer pour trier la colonne par ordre croissant",
                sortDescending: ": activer pour trier la colonne par ordre décroissant"
            },
        },
        "pagingType": "full_numbers",
        "pageLength": 10,
        "info": false,
        responsive: true,
        "dom": '<"top"i>frt<"bottom"p><"clear">'

    });

    $('#lesFraisHF').change(function () {
        $.ajax({
            url: "maj",
            dataType: "json",
            type: "POST",
            data: {
                'idFrais': idfrais,
                'etat': $('#choixEtat').val()
            },
            success: function (data) {
                window.console.log('success !!!' + data);
            },
        });
    });

    $('#lstMois').change(function () {
        $.ajax({
            url: "",
            dataType: "json",
            type: "POST",
            data: {
                'choixMois': $('#choixMois').val()
            },
            success: function (reponse) {
                $('#lstVisiteur').empty();
                $.each(reponse, function (index, element) {
                    $('#lstVisiteur').append('<option value="'+ element.id +'" selected="selected">'+ element.prenom +' '+ element.nom +' </option>');
                });
            }
        });
    });
    
        $('#lstMoisPaiement').change(function () {
        $.ajax({
            url: "",
            dataType: "json",
            type: "POST",
            data: {
                'choixMois': $('#choixMois').val()
            },
            success: function (reponse) {
                $('#lstVisiteur').empty();
                $.each(reponse, function (index, element) {
                    $('#lstVisiteur').append('<option value="'+ element.id +'" selected="selected">'+ element.prenom +' '+ element.nom +' </option>');
                });
            }
        });
    });

});