<p>The 3D roof designer shows the customer exactly what will go on their roof: the panel layout, the shadows from the water tank, staircase room, parapet and trees through the year, and how many units the system will really make. A quotation can then be made from the design in one click.</p>
<h4>Make a design</h4>
<ol class="steps">
    <li>Open the lead and click <span class="btnname">Design roof in 3D</span>, or <span class="path">Sales → 3D Roof Designs</span> → <span class="btnname">New design</span>.</li>
    <li><strong>Location</strong>: type the latitude and longitude, or stand on the roof with your phone and tap <i class="bi bi-geo-alt"></i>. The location sets the sun path and the solar data (kWh per kW per year).</li>
    <li><strong>Roof</strong>: enter width × depth and click <i class="bi bi-square"></i> (rectangle) or <i class="bi bi-bounding-box-circles"></i> (L-shape), or click <span class="btnname">Draw outline</span> and click each roof corner (click the first corner again to finish). Drag a corner to move it; click an edge length to type the exact metres. Use <i class="bi bi-arrow-counterclockwise"></i> <i class="bi bi-arrow-clockwise"></i> if the building is not square to north. Set the building height and the parapet wall height.</li>
    <li><strong>On the roof</strong>: add the <em>water tank</em>, <em>staircase room</em> and nearby <em>trees / poles</em> (trees are measured from the ground). Drag them to the right place on the plan and type their size and height.</li>
    <li><strong>Panels</strong>: pick the panel (items with a wattage in their name, e.g. "545W", appear here), orientation, tilt, facing and structure height. The roof is filled automatically, keeping the edge setback and a gap around obstacles. <em>Auto row gap</em> keeps rows from shading each other from 9 AM to 3 PM on the shortest day. Click any panel to remove it (click again to bring it back); <span class="btnname">Re-fill roof</span> starts again.</li>
    <li>Open <span class="btnname">3D view</span>: turn and zoom with the mouse or fingers. Choose a month and move the time slider (or <span class="btnname">Play day</span>) to see the shadows move.</li>
    <li>Click <span class="btnname">Run shading analysis</span>. The whole year is checked hour by hour; <em>Shading colours</em> shows each panel from green (no loss) to red (heavy loss).</li>
    <li><span class="btnname">Save</span>. The design gets a number like <code>RD/2026-27/0001</code> and a 3D picture.</li>
</ol>
<h4>The results</h4>
<ul>
    <li><strong>System size</strong> = panels × watt.</li>
    <li><strong>Generation / year</strong> = size × kWh per kW for this location, tilt and facing × (1 − shading loss). The kWh per kW comes from PVGIS (European Commission solar data, free, worldwide). If PVGIS cannot be reached, the <em>units per kW per day</em> from <span class="path">Settings → Quotation</span> is used and the screen says so.</li>
    <li><strong>Shading loss</strong> counts the shadows of everything you added. It assumes about 80% of the year's sunlight is direct; cloudy-day (diffuse) light is not blocked.</li>
</ul>
<h4>Quotation from the design</h4>
<ol class="steps">
    <li>Click <span class="btnname">Create quotation from design</span>.</li>
    <li>The quotation form opens with the kW, the kit for that size, and <em>units per kW per day</em> taken from the design's real generation, so the savings and payback match this roof.</li>
    <li>Fill the lines (<span class="btnname">Fill as B2C package</span> or <span class="btnname">Load items</span>) and save. The 3D picture is attached as the <em>Proposed layout</em> on the quotation PDF and the customer's link.</li>
</ol>
<h4>Good to know</h4>
<ul>
    <li>Who can design is set in <span class="path">Administration → Roles &amp; Access</span> (<em>3D roof designs: view / create</em>). Sales and surveyors have it by default.</li>
    <li>The designer works on a phone, so a surveyor can draw the roof on site.</li>
    <li>Coming next: the satellite photo of the house under the plan, address search and automatic roof detection (needs a Google Maps key in Settings).</li>
</ul>
