{{ search_form }}
<table class="table table-hover table-striped">
    <caption>{{ 'MeetingsFound'|get_lang }}</caption>
    <thead>
        <tr>
            <th>{{ 'StartTime'|get_lang }}</th>
            <th>{{ 'Course'|get_lang }}</th>
            <th>{{ 'Session'|get_lang }}</th>
            <th>{{ 'Topic'|get_lang }}</th>
            {% if recordings %}
            <th>{{ 'Recordings'|get_lang }}</th>
            {% endif %}
            <th></th>
        </tr>
    </thead>
        <tbody>
        {% for meeting in meetings %}
            <tr>
                <td>{{ meeting.formattedStartTime }}</td>
                <td>{{ meeting.course ? meeting.course.title : '-' }}</td>
                <td>{{ meeting.session ? meeting.session.name : '-' }}</td>
                <td>{{ meeting.topic }}</td>
                {% if recordings %}
                <td>
                    {% for recording in recordings %}
                    {% if recording.id == meeting.id %}
                    <dl>
                        <dt>
                            {{ recording.formattedStartTime }}
                            ({{ recording.formattedDuration }})
                        </dt>
                        <dd>
                            <ul>
                                {% for file in recording.recording_files %}
                                <li>
                                    {{ file.recording_type }}.{{ file.file_type }}
                                    ({{ file.formattedFileSize }})
                                </li>
                                {% endfor %}
                            </ul>
                        </dd>
                    </dl>
                    {% endif %}
                    {% endfor %}
                </td>
                {% endif %}
                <td>
                    <a class="btn" href="meeting_from_admin.php?meetingId={{ meeting.id }}">
                        {{ 'Details'|get_lang }}
                    </a>
                </td>
            </tr>
        {% endfor %}
    </tbody>
</table>