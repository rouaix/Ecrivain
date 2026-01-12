<?php
/* Mindmap view for a project. Variables: $project, $characters */
?>
<style>
    text {}
</style>
<h2>Carte mentale : <?php echo htmlspecialchars($project['title']); ?></h2>
<p><a class="button" href="<?php echo $data['base']; ?>/project/<?php echo $project['id']; ?>">Retour au projet</a></p>
<div id="mindmap" style="width:100%; height:500px; border:1px solid #ccc;"></div>
<p>Cette carte mentale est une représentation simplifiée où chaque personnage est relié au projet central. Pour une
    véritable mindmap interactive avec relations personnalisées, vous pouvez enrichir ce code en ajoutant des liaisons
    entre personnages et en enregistrant ces liens en base.</p>
<script src="https://cdnjs.cloudflare.com/ajax/libs/d3/7.8.5/d3.min.js"></script>
<script>
    // Build nodes and links for mindmap
    var data = {
        nodes: [
            { id: 'project', name: '<?php echo addslashes($project['title']); ?>', type: 'project' },
            <?php foreach ($characters as $c): ?>
                                                                                                                                    { id: 'char_<?php echo (int) $c['id']; ?>', name: '<?php echo addslashes($c['name']); ?>', type: 'character' },
            <?php endforeach; ?>
        ],
        links: [
            <?php foreach ($characters as $c): ?>
                                                                                                                                    { source: 'project', target: 'char_<?php echo (int) $c['id']; ?>' },
            <?php endforeach; ?>
        ]
    };

    var width = document.getElementById('mindmap').clientWidth;
    var height = document.getElementById('mindmap').clientHeight;

    var svg = d3.select('#mindmap').append('svg')
        .attr('width', width)
        .attr('height', height);

    var simulation = d3.forceSimulation(data.nodes)
        .force('link', d3.forceLink(data.links).id(function (d) { return d.id; }).distance(150))
        .force('charge', d3.forceManyBody().strength(-400))
        .force('center', d3.forceCenter(width / 2, height / 2));

    // Draw links
    var link = svg.append('g')
        .attr('stroke', '#aaa')
        .selectAll('line')
        .data(data.links)
        .enter().append('line')
        .attr('stroke-width', 1.5);

    // Draw nodes
    var node = svg.append('g')
        .selectAll('g')
        .data(data.nodes)
        .enter().append('g')
        .call(d3.drag()
            .on('start', dragstarted)
            .on('drag', dragged)
            .on('end', dragended));

    // Append text first to measure it
    var labels = node.append('text')
        .attr('dy', '.35em')
        .attr('text-anchor', 'middle')
        .attr('fill', '#fff')
        .style('font-size', '14px')
        .style('font-family', 'sans-serif')
        .text(function (d) { return d.name; });

    // Function to wrap text
    function wrap(text, width) {
        text.each(function() {
            var text = d3.select(this),
                words = text.text().split(/\s+/).reverse(),
                word,
                line = [],
                lineNumber = 0,
                lineHeight = 1.1, // ems
                y = text.attr("y"),
                dy = parseFloat(text.attr("dy")),
                tspan = text.text(null).append("tspan").attr("x", 0).attr("y", y).attr("dy", dy + "em");
            while (word = words.pop()) {
                line.push(word);
                tspan.text(line.join(" "));
                if (tspan.node().getComputedTextLength() > width) {
                    line.pop();
                    tspan.text(line.join(" "));
                    line = [word];
                    tspan = text.append("tspan").attr("x", 0).attr("y", y).attr("dy", ++lineNumber * lineHeight + dy + "em").text(word);
                }
            }
        });
    }

    // Apply wrap to labels
    labels.call(wrap, 120);

    // Append rectangles based on text size
    node.insert('rect', 'text')
        .attr('fill', function (d) { return d.type === 'project' ? '#4CAF50' : '#2196F3'; })
        .attr('rx', 6)
        .attr('ry', 6)
        .each(function(d) {
            var g = d3.select(this.parentNode);
            var textBBox = g.select('text').node().getBBox();
            d3.select(this)
                .attr('x', textBBox.x - 10)
                .attr('y', textBBox.y - 5)
                .attr('width', textBBox.width + 20)
                .attr('height', textBBox.height + 10);
        });

    simulation.on('tick', function () {
        link.attr('x1', function (d) { return d.source.x; })
            .attr('y1', function (d) { return d.source.y; })
            .attr('x2', function (d) { return d.target.x; })
            .attr('y2', function (d) { return d.target.y; });
        node.attr('transform', function (d) { return 'translate(' + d.x + ',' + d.y + ')'; });
    });

    function dragstarted(event, d) {
        if (!event.active) simulation.alphaTarget(0.3).restart();
        d.fx = d.x;
        d.fy = d.y;
    }
    function dragged(event, d) {
        d.fx = event.x;
        d.fy = event.y;
    }
    function dragended(event, d) {
        if (!event.active) simulation.alphaTarget(0);
        d.fx = null;
        d.fy = null;
    }
</script>