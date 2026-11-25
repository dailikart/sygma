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
<h4>Draw over a photo of the roof</h4>
<p>A photo taken from above makes tracing exact: a drone photo, a photo from a taller building next door, or a scan of the building plan.</p>
<ol class="steps">
    <li>In <strong>Photos</strong> click <span class="btnname">Upload photo</span>. It appears under the plan.</li>
    <li>Click <span class="btnname">Set scale</span>, click two points on the photo whose real distance you know (e.g. the two ends of the parapet) and type the metres. The photo now has the right size.</li>
    <li>Use <em>Turn photo</em> until north is up (the red N), and <span class="btnname">Move photo</span> to slide it if needed. <em>See-through</em> fades it.</li>
    <li>Click <span class="btnname">Draw outline</span> and click the roof corners on the photo; move the tank and staircase room onto their places in the photo.</li>
</ol>
<p>The photo is saved with the design and also shows in the 3D view (on the roof and the ground around it).</p>
<h4>Site photos</h4>
<p>In <strong>Photos → Add photos</strong> attach any number of photos for the team: roof, tank, staircase, EB meter, the view south. On a phone this opens the camera. If a photo has a GPS position and the design has no location yet, you are asked whether to use it.</p>
<h4>Google map, satellite and roof detection</h4>
<p>With a Google Maps key in <span class="path">Settings → Google Maps (3D roof design)</span>:</p>
<ul>
    <li><i class="bi bi-map"></i> <strong>Find on map</strong>: search the address, drag the map until the red cross is on the roof, <span class="btnname">Use this spot</span>. The location is set and the satellite photo appears under the plan at the right scale, north up.</li>
    <li><span class="btnname">Satellite</span>: show the satellite photo for the site location. It is shown live from Google and is not saved in the design or the 3D picture (Google's terms).</li>
    <li><span class="btnname">Detect roof</span>: Google Solar finds the building and gives the roof outline (approximate, as a box around the roof parts), whether it is flat or sloped, and Google's own estimate of panels and kWh. Drag the corners to match the photo exactly. Not every building in India is covered yet; if it is not, trace it over the satellite photo.</li>
</ul>
<h5>Getting the Google key (once, by the administrator)</h5>
<ol class="steps">
    <li>Open <code>console.cloud.google.com</code>, create a project (e.g. "Sygma Solar Suite") and add a <strong>billing account</strong>. Google gives a free monthly allowance for each API; set a budget alert under Billing.</li>
    <li><span class="path">APIs &amp; Services → Library</span>: enable <strong>Maps JavaScript API</strong>, <strong>Places API (New)</strong>, <strong>Maps Static API</strong>, <strong>Solar API</strong> and <strong>Geocoding API</strong>.</li>
    <li><span class="path">APIs &amp; Services → Credentials → Create credentials → API key</span>. Edit the key: <em>Application restrictions → Websites</em>: add your ERP address, e.g. <code>https://sygma.instivision.com/*</code>. <em>API restrictions</em>: the five APIs above.</li>
    <li>Paste the key in <span class="path">Administration → Settings → Google Maps (3D roof design)</span> and save. (On the demo site, where Settings is view-only, put <code>google.mapsKey = 'your-key'</code> in <code>.env</code> instead.)</li>
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
</ul>
